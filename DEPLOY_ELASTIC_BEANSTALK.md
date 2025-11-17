# Deploy no AWS Elastic Beanstalk

Este guia descreve como preparar e publicar o backend Laravel no AWS Elastic Beanstalk (EB).

## Pré-requisitos

-   AWS CLI configurado (`aws configure`) com um usuário IAM que possua permissões para Elastic Beanstalk, S3, EC2, RDS, CloudWatch Logs e IAM PassRole.
-   EB CLI instalada (`pipx install awsebcli` ou `pip install awsebcli`).
-   Bucket S3 para armazenar os pacotes de deploy (criado automaticamente no primeiro `eb init`).
-   Banco de dados configurado (RDS ou outro) e credenciais acessíveis via variáveis de ambiente.

## Estrutura adicionada ao projeto

-   `.ebextensions/00_laravel.config`: define o `document_root`, ajusta limites de memória e garante permissões de escrita (storage/bootstrap/cache).
-   `.platform/hooks/postdeploy/10_artisan.sh`: roda comandos críticos após cada deploy (`php artisan optimize`, `migrate`, `storage:link` e ajustes de permissão).
-   `.ebignore`: evita enviar arquivos desnecessários (tests, logs, node_modules etc.) ao criar o bundle de deploy.

## Configuração inicial (`eb init`)

1. Acesse o diretório do backend:
    ```bash
    cd /Users/fehneves/GitHub/clinicavoare/clinicavoare-backend
    ```
2. Execute o assistente:
    ```bash
    eb init
    ```
3. Responda:
    - **Região**: escolha a região onde o ambiente será criado.
    - **Aplicação**: crie uma nova ou selecione existente.
    - **Plataforma**: `PHP` (Amazon Linux 2023) com a versão de PHP compatível (>= 8.2).
    - **Código-fonte**: aceite a detecção automática.
4. Quando perguntado sobre _CodeCommit_ e _SSH_, responda conforme necessidade do projeto.

## Criação do ambiente (`eb create`)

1. Crie um ambiente (ex.: `clinicavoare-api-prod`):
    ```bash
    eb create clinicavoare-api-prod \
      --single \
      --instance-types t3.small \
      --scale 1 \
      --elb-type application
    ```
2. Durante o processo, o EB provisionará EC2, Security Groups e demais recursos. Aguarde o status `Ready`.

## Variáveis de ambiente

Defina as variáveis sensíveis pelo EB (Console ou CLI). Exemplos mínimos:

-   `APP_KEY`
-   `APP_URL`
-   `APP_ENV=production`
-   `APP_DEBUG=false`
-   `LOG_CHANNEL=stack`
-   `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
-   `QUEUE_CONNECTION=database` ou outro driver escolhido
-   Qualquer outra variável usada pelo `.env`

CLI (exemplo):

```bash
eb setenv \
  APP_ENV=production \
  APP_DEBUG=false \
  APP_URL=https://api.clinicavoare.com \
  LOG_CHANNEL=stack \
  DB_CONNECTION=mysql \
  DB_HOST=xxx.rds.amazonaws.com \
  DB_PORT=3306 \
  DB_DATABASE=clinicavoare \
  DB_USERNAME=admin \
  DB_PASSWORD=******** \
  QUEUE_CONNECTION=database
```

## Deploy

1. Gere um pacote (o EB CLI usa `.ebignore` automaticamente):
    ```bash
    eb deploy
    ```
2. Aguarde o processo completar. O roteiro pós-deploy executará `migrate` e `optimize`.
3. Verifique os logs:
    ```bash
    eb logs
    ```

## Boas práticas adicionais

-   **Banco de dados**: utilize RDS em sub-redes privadas e configure o Security Group para aceitar apenas a VPC do EB.
-   **Cache/Sessions**: considere Amazon ElastiCache ou DynamoDB para ambientes multi-instância.
-   **Logs**: habilite streaming para CloudWatch Logs em `eb console` > _Configuration_ > _Software_.
-   **Certificado SSL**: configure via Load Balancer (Console) ou `eb setenv` com `AWS Certificate Manager`.
-   **Escalonamento**: ajuste `--scale` ou configure _Auto Scaling_ no console conforme demanda.

## Solução de problemas

-   Erros no deploy normalmente aparecem em `/var/log/eb-engine.log` ou `/var/log/web.stdout.log`. Utilize `eb ssh` para inspeção.
-   Se `php artisan migrate` falhar, o deploy é interrompido; ajuste o script conforme sua estratégia (ex.: permitir falha com `|| true` e rodar manualmente).
-   Certifique-se de que `APP_KEY` está definido antes do primeiro deploy (use `php artisan key:generate --show` localmente e exporte para o EB).

---

Siga estes passos para preparar rapidamente o ambiente e realizar deploys consistentes no Elastic Beanstalk.
