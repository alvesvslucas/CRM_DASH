# CREDASH — Dashboard por Perfil e Setor

Aplicação PHP com autenticação e encaminhamento de usuários para painéis conforme perfil e setor.

## Fluxo de acesso

`index.php` redireciona para `pages/login.php`. O formulário envia usuário e senha a `backend/login_process.php`, que consulta o banco, verifica a senha com `password_verify` e mantém perfil/setor na sessão.

O código possui encaminhamentos para administrador, supervisor e TV indoor. Os destinos são definidos por constantes de caminhos, incluindo setores como Cartão, Energia, Consignado, Backoffice e FGTS.

## Estrutura

| Caminho | Finalidade |
| --- | --- |
| `pages/` | Telas da aplicação |
| `backend/` | Processamento das requisições |
| `db/` | Integração com banco |
| `includes/` e `assets/` | Componentes e recursos visuais |
| `absoluto.php` | Definições de caminhos usadas nas páginas |
| `db/config.php` | Conexão requerida pelo processamento de login |

## Ambiente local

É necessário PHP com sessões, MySQL e `mysqli`. O login usa `mysqli_stmt::get_result`, que também depende de suporte `mysqlnd`. A versão de PHP compatível e o processo de criação do banco ainda precisam ser validados.

```sh
git clone https://github.com/alvesvslucas/CRM_DASH.git
cd CRM_DASH
```

1. Prepare uma base de teste com o esquema completo. O login espera a tabela `users`; este README não cria tabelas nem fornece credenciais.
2. Configure uma conexão local em `db/config.php` e revise as constantes de caminhos de `absoluto.php` para o endereço local.
3. Inicie o servidor apenas para desenvolvimento:

```sh
php -S 127.0.0.1:8080 -t .
```

Abra `http://127.0.0.1:8080/` com uma conta de teste cujo perfil e setor estejam configurados.

## Estado

Login, destinos de cada perfil e permissões dos painéis não foram executados nesta revisão documental. A configuração de caminhos e o esquema do banco são etapas necessárias para reproduzir o ambiente.
