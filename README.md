# Amazon SES Bundle for Mautic 6.0

🚀 **Plugin Amazon SES completo** para Mautic 6.0 com funcionalidades avançadas de email transport.

## 🎯 **Características Principais**

### ✅ **Configurações Dinâmicas via UI do Mautic**
- 🔧 **Interface nativa**: Configure através do painel do Mautic
- 🔄 **DSN automático**: Mautic gera automaticamente `ses+api://ACCESS_KEY:SECRET_KEY@default?region=REGION`
- 🌍 **Multi-idioma**: Português e Inglês
- 📊 **EventSubscriber nativo**: Processamento automático de webhooks seguindo padrão oficial

### ✅ **Processamento Avançado de Callbacks**
- 📨 **Bounces automáticos**: Marcação automática de contatos como bounced
- 🚫 **Complaints automáticos**: Marcação automática de contatos como unsubscribed  
- 🔔 **Confirmação SNS**: Confirmação automática de subscrições SNS
- 🎯 **TransportCallback nativo**: Integração completa com sistema de callback do Mautic
- 🔒 **Validação DSN**: Só processa callbacks quando Amazon SES está ativo

## 📦 **Instalação**

### 1. Via Docker (Atual)
```bash
# Plugin já está instalado no exemplo custom-plugins
docker-compose up -d --build
```

### 2. Instalação Manual
```bash
# Copiar plugin para o diretório de plugins do Mautic
cp -r AmazonSESBundle /var/www/html/docroot/plugins/

# Instalar dependências AWS SDK (se não instaladas)
composer require aws/aws-sdk-php

# Recarregar plugins
php bin/console mautic:plugins:reload

# Limpar cache
php bin/console cache:clear
```

## ⚙️ **Configuração**

### 1. **Mautic UI (Recomendado)**
1. Acesse **Configurações** → **Configuração** → **Configurações de Email**
2. Em **Esquema do Transport de Email**, selecione: `ses+api`
3. Configure os campos:
   - **Host**: `default`
   - **Porta**: `465`
   - **Usuário**: `SUA_AWS_ACCESS_KEY`
   - **Senha**: `SUA_AWS_SECRET_KEY`
   - **Região**: `us-east-1` (ou sua região preferida)

### 2. **Via DSN Direto**
```bash
# Exemplo de DSN completo
MAILER_DSN="ses+api://ACCESS_KEY:SECRET_KEY@default?region=us-east-1"
```

## 🔔 **Configuração AWS SNS para Callbacks**

### 1. **Criar Tópico SNS**
```bash
# No AWS Console ou CLI
aws sns create-topic --name mautic-ses-events
```

### 2. **Configurar SES para Enviar para SNS**
```bash
# Associar SES com SNS para bounces e complaints
aws ses put-configuration-set-event-destination \
  --configuration-set-name your-config-set \
  --event-destination Name=sns-destination,Enabled=true,SNSDestination={TopicARN=arn:aws:sns:region:account:mautic-ses-events}
```

### 3. **Configurar Subscrição HTTPS**
- **Protocolo**: HTTPS
- **Endpoint**: `https://seu-mautic.com/mailer/callback`
- **Habilitar raw message delivery**: ✅ **SIM**

## 🎯 **EventSubscriber - Padrão Oficial**

O plugin implementa `CallbackSubscriber` seguindo **exatamente** o padrão do plugin oficial:

### **Eventos Suportados**
```php
EmailEvents::ON_TRANSPORT_WEBHOOK => 'processCallbackRequest'
```

### **Tipos de Callback Processados**
1. **SubscriptionConfirmation**: Confirmação automática de subscrições SNS
2. **Bounce**: Processamento de bounces permanentes
3. **Complaint**: Processamento de reclamações/spam reports
4. **Notification**: Processamento de notificações gerais

### **Integração com TransportCallback**
```php
// Bounce automático
$this->transportCallback->addFailureByAddress(
    $address->getAddress(),
    $diagnosticCode,
    DoNotContact::BOUNCED,
    $emailId
);

// Complaint automático
$this->transportCallback->addFailureByAddress(
    $address->getAddress(),
    $reason,
    DoNotContact::UNSUBSCRIBED,
    $emailId
);
```

## 🛠️ **Arquitetura do Plugin**

```
AmazonSESBundle/
├── AmazonSESBundle.php              # Classe principal
├── Config/
│   ├── config.php                   # Configuração do plugin
│   └── services.php                 # Serviços autowired
├── DependencyInjection/
│   └── AmazonSESExtension.php       # Extension DI
├── EventSubscriber/
│   └── CallbackSubscriber.php       # ⭐ Processamento webhooks
├── Transport/
│   ├── AmazonSesTransport.php       # Transport principal
│   └── AmazonSesTransportFactory.php # Factory dinâmica
└── Translations/
    ├── en_US/messages.ini           # Traduções inglês
    └── pt_BR/messages.ini           # Traduções português
```

## 🧪 **Teste de Funcionalidade**

### 1. **Teste de Envio**
```bash
# Via CLI do Mautic (dentro do container)
php bin/console mautic:emails:send --quiet
```

### 2. **Teste de Callback**
```bash
# Simular callback SNS
curl -X POST https://seu-mautic.com/mailer/callback \
  -H "Content-Type: application/json" \
  -d '{"Type": "SubscriptionConfirmation", "SubscribeURL": "https://sns.amazonaws.com/..."}'
```

### 3. **Logs de Verificação**
```bash
# Ver logs do EventSubscriber
docker-compose logs mautic_web | grep "Amazon SES"
```

## 🔍 **Diferenças do Plugin Oficial**

| Aspecto | Plugin Oficial | Nossa Implementação |
|---------|---------------|---------------------|
| **Base** | `symfony/amazon-mailer` | `aws/aws-sdk-php` |
| **EventSubscriber** | ✅ Completo | ✅ **Implementado** |
| **TransportCallback** | ✅ Nativo | ✅ **Implementado** |
| **Dependency Injection** | Extension + autowiring | Extension + autowiring |
| **Suporte Mautic** | 5.x | **6.x** |
| **Traduções** | EN | **PT-BR + EN** |

## 🚀 **Pronto para Produção**

O plugin está **100% funcional** e pronto para:

1. ✅ **Envio de emails** em produção
2. ✅ **Processamento automático** de bounces/complaints
3. ✅ **Configuração dinâmica** via UI do Mautic
4. ✅ **Integração SNS** completa
5. ✅ **Conformidade** com padrões oficiais do Mautic

---

## 📋 **Recursos Implementados**

- [x] Transport Factory com autowiring
- [x] Configurações dinâmicas via DSN
- [x] EventSubscriber para webhooks
- [x] TransportCallback automático
- [x] Processamento de bounces permanentes
- [x] Processamento de complaints
- [x] Confirmação automática SNS
- [x] Traduções multi-idioma
- [x] Logs estruturados
- [x] Validação DSN
- [x] Seguir padrão oficial

**Plugin Amazon SES para Mautic 6.0 - Implementação Completa** 🎉 