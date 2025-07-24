# Amazon SES Bundle for Mautic 6.0

🚀 **Plugin Amazon SES completo** para Mautic 6.0 com funcionalidades avançadas de email transport e processamento de callbacks.

## 🎯 **Características Principais**

### ✅ **Configurações Dinâmicas via UI do Mautic**
- 🔧 **Interface nativa**: Configure através do painel administrativo do Mautic
- 🔄 **DSN automático**: Integração completa com sistema de transporte do Mautic
- 🌍 **Multi-idioma**: Suporte completo em Português e Inglês
- 📊 **EventSubscriber nativo**: Processamento automático de webhooks

### ✅ **Processamento Avançado de Callbacks**
- 📨 **Bounces automáticos**: Marcação automática de contatos como bounced
- 🚫 **Complaints automáticos**: Marcação automática de contatos como unsubscribed  
- 🔔 **Confirmação SNS**: Confirmação automática de subscrições SNS
- 🎯 **TransportCallback nativo**: Integração completa com sistema de callback do Mautic
- 🔒 **Validação DSN**: Processa callbacks apenas quando Amazon SES está ativo

## 📦 **Instalação**

### Requisitos
- Mautic 6.0+
- PHP 8.1+
- AWS SDK PHP

### Instalação via Composer
```bash
composer require rhafaman/mautic-amazon-ses-bundle
```

### Instalação Manual
```bash
# 1. Baixar e extrair o plugin
wget https://github.com/rhafaman/mautic-amazon-ses-bundle/archive/main.zip

# 2. Extrair no diretório de plugins do Mautic
unzip main.zip -d /var/www/html/plugins/
mv /var/www/html/plugins/mautic-amazon-ses-bundle-main /var/www/html/plugins/AmazonSESBundle

# 3. Instalar dependências AWS SDK (se necessário)
composer require aws/aws-sdk-php

# 4. Recarregar plugins
php bin/console mautic:plugins:reload

# 5. Limpar cache
php bin/console cache:clear
```

## ⚙️ **Configuração**

### 1. **Configuração via Interface do Mautic (Recomendado)**
1. Acesse **Configurações** → **Configuração** → **Configurações de Email**
2. Em **Esquema do Transport de Email**, selecione: `ses+smtp`
3. Configure os campos:
   - **Host**: `default`
   - **Porta**: `465`
   - **Usuário**: `SUA_AWS_ACCESS_KEY`
   - **Senha**: `SUA_AWS_SECRET_KEY`
   - **Região**: Sua região AWS (ex: `us-east-1`)

### 2. **Configuração via Variável de Ambiente**
```bash
# Defina a variável MAILER_DSN no seu .env
MAILER_DSN="ses+api://ACCESS_KEY:SECRET_KEY@default?region=us-east-1"
```

## 🔔 **Configuração AWS SNS para Callbacks**

### 1. **Criar Tópico SNS**
```bash
aws sns create-topic --name mautic-ses-events --region us-east-1
```

### 2. **Configurar SES Configuration Set**
```bash
# Criar configuration set
aws sesv2 create-configuration-set --configuration-set-name mautic-config

# Adicionar event destination
aws sesv2 create-configuration-set-event-destination \
  --configuration-set-name mautic-config \
  --event-destination-name sns-destination \
  --event-destination \
  'Enabled=true,MatchingEventTypes=bounce,complaint,delivery,SnsDestination={TopicArn=arn:aws:sns:us-east-1:ACCOUNT:mautic-ses-events}'
```

### 3. **Configurar Subscrição HTTPS no SNS**
- **Protocolo**: HTTPS
- **Endpoint**: `https://seu-dominio.com/mailer/callback`
- **Habilitar raw message delivery**: ✅ **Obrigatório**

## 🎯 **Processamento de Webhooks**

### **Eventos Suportados**
O plugin processa automaticamente os seguintes tipos de callback:

1. **SubscriptionConfirmation**: Confirmação automática de subscrições SNS
2. **Bounce**: Processamento de bounces permanentes e temporários
3. **Complaint**: Processamento de reclamações de spam
4. **Delivery**: Confirmações de entrega (opcional)

### **Endpoint de Callback**
```
POST https://seu-dominio.com/mailer/callback
Content-Type: application/json
```

## 🛠️ **Arquitetura do Plugin**

```
AmazonSESBundle/
├── AmazonSESBundle.php              # Classe principal do bundle
├── Config/
│   ├── config.php                   # Configurações do plugin
│   └── services.php                 # Definição de serviços
├── DependencyInjection/
│   └── AmazonSESExtension.php       # Extensão de injeção de dependência
├── EventSubscriber/
│   └── CallbackSubscriber.php       # Processamento de webhooks SNS
├── Services/
│   └── AmazonSES/                   # Classes de modelo para callbacks
└── Translations/
    ├── en_US/messages.ini           # Traduções em inglês
    └── pt_BR/messages.ini           # Traduções em português
```

## 🧪 **Teste e Verificação**

### 1. **Teste de Envio de Email**
```bash
# Enviar emails via CLI
php bin/console mautic:emails:send

# Verificar logs
tail -f var/logs/mautic_prod.log | grep -i "amazon\|ses"
```

### 2. **Teste de Callback SNS**
```bash
# Simular callback de confirmação
curl -X POST https://seu-dominio.com/mailer/callback \
  -H "Content-Type: application/json" \
  -d '{
    "Type": "SubscriptionConfirmation",
    "SubscribeURL": "https://sns.amazonaws.com/...",
    "TopicArn": "arn:aws:sns:us-east-1:123456789:mautic-ses-events"
  }'
```

### 3. **Verificação de Status**
```bash
# Verificar se o plugin está ativo
php bin/console mautic:plugins:list | grep -i amazon

# Verificar configuração de email
php bin/console debug:config
```

## 📊 **Funcionalidades Implementadas**

- [x] **Transport Factory** com autowiring completo
- [x] **Configurações dinâmicas** via DSN do Mautic
- [x] **EventSubscriber** para processamento de webhooks
- [x] **TransportCallback** automático
- [x] **Processamento de bounces** permanentes e temporários
- [x] **Processamento de complaints** automático
- [x] **Confirmação automática SNS** 
- [x] **Traduções** em português e inglês
- [x] **Logs estruturados** para debugging
- [x] **Validação de DSN** antes do processamento
- [x] **Compatibilidade** total com Mautic 6.0

## 🔧 **Suporte e Contribuição**

### Reportar Problemas
- GitHub Issues: https://github.com/rhafaman/mautic-amazon-ses-bundle/issues

### Contribuir
- Fork o projeto
- Crie uma branch para sua feature
- Faça commit com mensagens descritivas
- Abra um Pull Request

### Licença
GPL-3.0-or-later

---

**Amazon SES Bundle para Mautic 6.0 - Solução Completa de Email Transport** 🎉 