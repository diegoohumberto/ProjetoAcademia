<?php

//IMPORTA O ARQUIVO DE CONFIGURAÇÃO:
require '../_app/Config.inc.php';

$jSon = array();

$getPost = filter_input_array(INPUT_POST, FILTER_DEFAULT);

if (count($getPost) == 1):
    $getQuery = array_keys($getPost);

    $queryPesquisa = (is_int($getQuery[0]) ? $getQuery[0] : strip_tags(str_replace('_', ' ', $getQuery[0])));

    $buscarHistC = new Read;

    if ($queryPesquisa >= 1):
        $buscarHistC->FullRead("SELECT registros_catraca.idregistros_catraca, registros_catraca.idaluno_clientes, alunos_cliente.nome_aluno, "
                . "registros_catraca.hr_entrada_catraca, registros_catraca.hr_saida_catraca, registros_catraca.data_registro "
                . "FROM registros_catraca "
                . "INNER JOIN alunos_cliente ON registros_catraca.idaluno_clientes = alunos_cliente.idalunos_cliente "
                . "WHERE registros_catraca.idregistros_catraca = {$queryPesquisa}");
        $jSon = $buscarHistC->getResult();
    elseif ($queryPesquisa === 0):
        $buscarHistC->FullRead("SELECT registros_catraca.idregistros_catraca, registros_catraca.idaluno_clientes, alunos_cliente.nome_aluno, "
                . "registros_catraca.hr_entrada_catraca, registros_catraca.hr_saida_catraca, registros_catraca.data_registro "
                . "FROM registros_catraca "
                . "INNER JOIN alunos_cliente ON registros_catraca.idaluno_clientes = alunos_cliente.idalunos_cliente");
        $jSon = $buscarHistC->getResult();
    elseif (is_string($queryPesquisa)):
        $buscarHistC->FullRead("SELECT registros_catraca.idregistros_catraca, registros_catraca.idaluno_clientes, alunos_cliente.nome_aluno, "
                . "registros_catraca.hr_entrada_catraca, registros_catraca.hr_saida_catraca, registros_catraca.data_registro "
                . "FROM registros_catraca "
                . "INNER JOIN alunos_cliente ON registros_catraca.idaluno_clientes = alunos_cliente.idalunos_cliente "
                . "WHERE alunos_cliente.nome_aluno LIKE '%{$queryPesquisa}%'");
        $jSon = $buscarHistC->getResult();
    endif;
endif;

if (empty($getPost['callback'])):
    $jSon['trigger'] = "<div>Erro!</div>";
else:
    $Post = array_map("strip_tags", $getPost);

    $Action = $Post['callback'];

    unset($Post['callback']);

    switch ($Action):
        //CASO CALLBACK SEJÁ create-registro EXECUTA-SE A FUNÇÃO DE CADASTRO
        case 'create-registro':

            date_default_timezone_set('America/Sao_Paulo');
            $Post['data_registro'] = date("Y-m-d");
            $Post['hr_entrada_catraca'] = date("H:i:s");
            $Post['hr_saida_catraca'] = null;

            $idaluno_clientes = $Post['idaluno_clientes'];

            $ConsultaID = new Read;
            $ConsultaID->FullRead("SELECT * FROM alunos_cliente WHERE idalunos_cliente = {$idaluno_clientes}");
            $ConsultaID->getResult();
            if ($ConsultaID->getRowCount() <= 0):
                $jSon['inesistente'] = true;
                $jSon['clear'] = true;
                break;
            else:
                $Tabela = "registros_catraca";

                require '../_app/Conn/Create.class.php';

                //CONSULTA O STATUS DA MENSALIDADE DO ALUNO ESCOLHIDO:
                $ConsultaStatus = new Read;
                $ConsultaStatus->FullRead("SELECT mensalidades.idalunos_cliente, mensalidades.status_mens "
                        . "FROM mensalidades WHERE idalunos_cliente = {$idaluno_clientes}");
                //OBTENDO O ID DO REGISTRO ENCONTRADO:
                $ResultStatus = $ConsultaStatus->getResult();
                $status_mens = $ResultStatus[0]['status_mens'];


                //CASO O STATUS DA MENSALIDADE ESTEJA EM ABERTO A CATRACA LIBERA O ACESSO E CADASTRA UM NOVO REGISTRO:
                if ($status_mens == 'Em Aberto'):

                    //CONSULTANDO SE EXISTE UM REGISTRO DO ALUNO COM O HORÁRIO DE SAIDA VAZIO.
                    $consultaSaida = new Read;
                    $consultaSaida->FullRead("SELECT * FROM registros_catraca WHERE idaluno_clientes = {$idaluno_clientes} AND hr_saida_catraca IS NULL;");
                    //OBTENDO O ID DO REGISTRO ENCONTRADO: 
                    $resultSaida = $consultaSaida->getResult();

                    //CASO A HR DE SAIDA ESTEJA VAZIA CADASTRA UM NOVO REGISTRO:
                    if (!$consultaSaida->getResult()):
                        //var_dump($resultSaida);
                        $CadastrarRegistro = new Create;
                        $CadastrarRegistro->ExeCreate($Tabela, $Post);
                        $CadastrarRegistro->getResult();

                        $idregistros_catraca = $CadastrarRegistro->getResult();
                        $registroNovo = new Read;
                        $registroNovo->FullRead("SELECT alunos_cliente.idalunos_cliente, alunos_cliente.nome_aluno, registros_catraca.idregistros_catraca, "
                                . "registros_catraca.hr_entrada_catraca, registros_catraca.hr_saida_catraca, registros_catraca.data_registro " .
                                "FROM registros_catraca " .
                                "INNER JOIN alunos_cliente ON registros_catraca.idaluno_clientes = alunos_cliente.idalunos_cliente " .
                                "WHERE registros_catraca.idregistros_catraca = :idregistros_catraca", "idregistros_catraca={$idregistros_catraca}");

                        if ($registroNovo->getResult()):
                            $registro = $registroNovo->getResult();

                            $jSon['novoregistroC'] = $registro[0];
                            $jSon['sucesso'] = true;
                            $jSon['clear'] = true;
                        endif;


                    //CASO A HR DE SAIDA NÃO ESTEJA VAZIA É INFORMADO UMA MENSAGEM:
                    else:
                        //var_dump($resultSaida);
                        $jSon['informacao'] = true;
                        $jSon['clear'] = true;

                    //echo "Aluno esta presente";
                    endif;

                //CASO O STATUS DA MENSALIDADE ESTEJA PENDENTE A CATRACA LIBERA O ACESSO E CADASTRA UM NOVO REGISTRO, PORÉM ALERTA AO ALUNO:
                elseif ($status_mens == 'Pendente' || $status_mens == 'Alerta'):
                    //CONSULTANDO SE EXISTE UM REGISTRO DO ALUNO COM O HORÁRIO DE SAIDA VAZIO.
                    $consultaSaida = new Read;
                    $consultaSaida->FullRead("SELECT * FROM registros_catraca WHERE idaluno_clientes = {$idaluno_clientes} AND hr_saida_catraca IS NULL;");
                    //OBTENDO O ID DO REGISTRO ENCONTRADO: 
                    $resultSaida = $consultaSaida->getResult();

                    //CASO A HR DE SAIDA ESTEJA VAZIA CADASTRA UM NOVO REGISTRO:
                    if (!$consultaSaida->getResult()):
                        $CadastrarRegistro = new Create;
                        $CadastrarRegistro->ExeCreate($Tabela, $Post);
                        $CadastrarRegistro->getResult();

                        $idregistros_catraca = $CadastrarRegistro->getResult();
                        $registroNovo = new Read;
                        $registroNovo->FullRead("SELECT alunos_cliente.idalunos_cliente, alunos_cliente.nome_aluno, registros_catraca.idregistros_catraca, "
                                . "registros_catraca.hr_entrada_catraca, registros_catraca.hr_saida_catraca, registros_catraca.data_registro " .
                                "FROM registros_catraca " .
                                "INNER JOIN alunos_cliente ON registros_catraca.idaluno_clientes = alunos_cliente.idalunos_cliente " .
                                "WHERE registros_catraca.idregistros_catraca = :idregistros_catraca", "idregistros_catraca={$idregistros_catraca}");

                        if ($registroNovo->getResult()):
                            $registro = $registroNovo->getResult();

                            $jSon['novoregistroC'] = $registro[0];
                            $jSon['alerta'] = true;
                            $jSon['clear'] = true;
                        endif;

                    //CASO A HR DE SAIDA NÃO ESTEJA VAZIA É INFORMADO UMA MENSAGEM:
                    else:
                        //var_dump($resultSaida);
                        $jSon['informacao'] = true;
                        $jSon['clear'] = true;

                    //echo "Aluno esta presente";
                    endif;

                //CASO O STATUS DA MENSALIDADE ESTEJÁ VENCIDA A CATRACA BLOQUEIA O ACESSO:    
                elseif ($status_mens == 'Vencido'):

                    $jSon['erro'] = true;

                    $jSon['clear'] = true;

                else:
                //echo "INESISTENTE";

                endif;
            endif;

            break;

        //CASO O CALLBACK SEJÁ sair-catraca EXECUTA-SE A FUNÇÃO PARA ATUALIZAR DADOS
        case 'sair-catraca':

            $updateRegistro = array();
            $updateRegistro['idaluno_clientes'] = $Post['idaluno_clientes'];
            $updateRegistro['hr_saida_catraca'] = $Post['hr_saida_catraca'];
            unset($Post['hr_saida_catraca']);

            //ATRIBUINDO A HORA ATUAL A VARIAVEL.
            date_default_timezone_set('America/Sao_Paulo');
            $Post['hr_saida_catraca'] = date("H:i:s");

            //CONSULTANDO SE EXISTE UM REGISTRO DO ALUNO COM O HORÁRIO DE SAIDA VAZIO.
            $idaluno_clientes = $Post['idaluno_clientes'];
            $consultaRegistro = new Read;
            $consultaRegistro->FullRead("SELECT * FROM registros_catraca WHERE idaluno_clientes = {$idaluno_clientes} AND hr_saida_catraca IS NULL;");

            if ($consultaRegistro->getResult()):
                //OBTENDO O ID DO REGISTRO ENCONTRADO. 
                $resultConsulta = $consultaRegistro->getResult();
                $idregistros_catraca = $resultConsulta[0]['idregistros_catraca'];
                $Tabela = "registros_catraca";
                $sairCatraca = new Update;
                $sairCatraca->ExeUpdate($Tabela, $Post, "WHERE idregistros_catraca = :idregistros_catraca", "idregistros_catraca={$idregistros_catraca}");

                $sairCatraca->getResult();
                $readSaida = new Read;
                $readSaida->FullRead("SELECT alunos_cliente.idalunos_cliente, alunos_cliente.nome_aluno, registros_catraca.idregistros_catraca, "
                        . "registros_catraca.hr_entrada_catraca, registros_catraca.hr_saida_catraca, registros_catraca.data_registro " .
                        "FROM registros_catraca " .
                        "INNER JOIN alunos_cliente ON registros_catraca.idaluno_clientes = alunos_cliente.idalunos_cliente " .
                        "WHERE registros_catraca.idregistros_catraca = :idregistros_catraca", "idregistros_catraca={$idregistros_catraca}");
                $DadosSaida = $readSaida->getResult();

                //Check::DataBrasil($DadosSaida[0]['data_registro']);

                $jSon['saiu'] = true;
                $jSon['clear'] = true;
                $jSon['atualizaSaida'] = $DadosSaida[0];

            else:
                $jSon['fora'] = true;
                $jSon['clear'] = true;
            endif;



            break;
    endswitch;
endif;
echo json_encode($jSon);
