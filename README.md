# Plugin Amazon SES para Mautic 6

<p align="center">
<img src="Assets/img/icon.png" alt="Amazon SES" width="200"/>
</p>

<p align="center">
<strong>Plugin oficial para integração do Mautic 6 com Amazon Simple Email Service (SES)</strong>
</p>

---

## 🚀 **Recursos Aprimorados**

- ✅ **Suporte a múltiplos esquemas:** `ses+api` e `ses+smtp`
- ✅ **Processamento de callbacks SNS** para bounces e reclamações
- ✅ **Comando de debug avançado** com diagnóstico completo
- ✅ **Detecção automática de email remetente** - sem necessidade de configuração manual
- ✅ **Validação flexível de secret keys** (suporta chaves de 20-128 caracteres)
- ✅ **Testes reais de conexão AWS** com informações de quota
- ✅ **Debug automático de exceções de assinatura**
- ✅ **Testes de conectividade SMTP/SSL**

---

## 📦 **Instalação**

### 1. Copiar o Plugin
```bash
cp -r examples/custom-plugins/plugins/AmazonSESBundle /caminho/para/mautic/plugins/
```

### 2. Limpar Cache
```bash
php bin/console cache:clear
```

### 3. Instalar Plugin
```bash
php bin/console mautic:plugins:reload
```

### 4. Verificar Instalação
```bash
php bin/console mautic:amazon-ses:debug
```

---

## ⚙️ **Configuração**

### 🔧 **Opção 1: SES API (Recomendado)**

**Melhor performance e compatibilidade**

**Formato DSN:** `ses+api://ACCESS_KEY:SECRET_KEY@default?region=REGIAO`

1. **Navegue para:** Configuração → Configurações de Envio de Email
2. **Configure os campos:**

| Campo    | Valor                    |
| -------- | ------------------------ |
| Esquema  | `ses+api`               |
| Host     | `default`               |
| Porta    | `465`                   |
| Usuário  | `<aws-access-key>`      |
| Senha    | `<aws-secret-key>`      |
| Região   | `<aws-region>`          |

### 🔧 **Opção 2: SES SMTP**

**Compatível com configurações SMTP tradicionais**

**Formato DSN:** `ses+smtp://ACCESS_KEY:SECRET_KEY@email-smtp.REGIAO.amazonaws.com:587?region=REGIAO`

1. **Navegue para:** Configuração → Configurações de Envio de Email
2. **Configure os campos:**

| Campo    | Valor                                     |
| -------- | ----------------------------------------- |
| Esquema  | `ses+smtp`                               |
| Host     | `email-smtp.<regiao>.amazonaws.com`     |
| Porta    | `587` (STARTTLS) ou `465` (SSL)         |
| Usuário  | `<aws-access-key>`                      |
| Senha    | `<aws-secret-key>`                      |
| Região   | `<aws-region>`                          |

### 📝 **Notas sobre Credenciais**

- **Access Key:** Chave de acesso do usuário IAM AWS (tipicamente 20 caracteres)
- **Secret Key:** Chave secreta do usuário IAM AWS (pode ter 20-128 caracteres - todas suportadas)
- **Região:** Região AWS onde o SES está habilitado (`us-east-1`, `sa-east-1`, `eu-west-1`, etc.)
- **Caracteres Especiais:** Se sua secret key contém `+`, `/`, `=`, etc., use URL encoding

---

## 🔍 **Debug e Testes Avançados**

### 🛠️ **Comando de Debug Inteligente**

O plugin inclui um poderoso comando de debug com **detecção automática de configurações**:

```bash
# Análise completa da configuração (recomendado para começar)
php bin/console mautic:amazon-ses:debug

# Teste de conexão real com AWS SES
php bin/console mautic:amazon-ses:debug --test-connection

# Teste de conectividade SMTP (diagnóstico de rede)
php bin/console mautic:amazon-ses:debug --test-smtp-connectivity

# Envio de email de teste (com detecção automática do remetente)
php bin/console mautic:amazon-ses:debug --test-email=seu-email@dominio.com

# Teste completo com email específico do remetente
php bin/console mautic:amazon-ses:debug --test-email=destino@dominio.com --from=remetente@dominio-verificado.com

# Teste de reconhecimento de esquemas
php bin/console mautic:amazon-ses:debug --test-schemes
```

### 🎯 **Detecção Automática de Email Remetente**

**✨ NOVA FUNCIONALIDADE:** O plugin agora detecta automaticamente o email remetente!

**Ordem de prioridade de detecção:**
1. **`mailer_from_email`** - Configuração padrão do Mautic
2. **`webmaster_email`** - Email do administrador do sistema
3. **Email do DSN** - Se o usuário do DSN for um email válido
4. **Fallback automático** - Geração baseada no host atual

**Exemplo de saída:**
```bash
📧 Email Remetente Padrão:
✅ Email detectado: admin@meusite.com.br
📍 Fonte: Configuração mailer_from_email do Mautic
💡 Use este email com: --from=admin@meusite.com.br
```

### 📊 **Recursos do Debug**

O comando de debug oferece análise completa:

- ✅ **Validação de DSN:** Verifica configurações `ses+api` e `ses+smtp`
- ✅ **Teste de Conexão AWS:** Conexão real ao SES com informações de quota
- ✅ **Validação de Credenciais:** Suporte a chaves de diversos tamanhos
- ✅ **Conectividade de Rede:** Testa conexão com endpoints AWS
- ✅ **Teste de Email:** Envia emails reais através da configuração
- ✅ **Diagnóstico de Erros:** Troubleshooting específico para problemas comuns
- ✅ **Detecção de Email:** Identifica automaticamente email remetente
- ✅ **Análise de Esquemas:** Verifica suporte a transportes

---

## 📡 **Configuração AWS SNS**

Para processar bounces e reclamações automaticamente:

### 1. **Criar Tópico SNS**
- Vincule à sua identidade SES
- Configure notificações de bounce e complaint

### 2. **Configurar Subscrição**
- **Protocolo:** `HTTPS`
- **⚠️ IMPORTANTE:** Habilite "raw message delivery"
- **Endpoint:** `https://seu-dominio-mautic.com/mailer/callback`

### 3. **Confirmação Automática**
O plugin confirma automaticamente as subscrições SNS

---

## 🚨 **Troubleshooting**

### **1. Problemas de Timeout de Conexão (SSL/TLS)**

**Erro típico:**
```
Connection could not be established with host "ssl://email-smtp.us-east-1.amazonaws.com:465"
```

**Soluções:**

**🔹 Problema de Porta 465:**
```bash
# Teste conectividade SMTP
php bin/console mautic:amazon-ses:debug --test-smtp-connectivity

# Mude para porta 587 (recomendado)
ses+smtp://ACCESS_KEY:SECRET_KEY@email-smtp.us-east-1.amazonaws.com:587?region=us-east-1
```

**🔹 Bloqueio de Firewall:**
- Contate seu provedor de hospedagem
- Verifique se as portas 587 e/ou 465 estão abertas

**🔹 Hospedagem Compartilhada:**
- Use esquema `ses+api` em vez de `ses+smtp`
- Alguns provedores oferecem porta alternativa 2587

### **2. InvalidSignatureException**

**Diagnóstico automático:**
```bash
php bin/console mautic:amazon-ses:debug --test-connection
```

**Soluções:**
- URL-encode sua secret key se contém caracteres especiais
- Verifique se as credenciais AWS estão corretas

**Exemplo de encoding:**
```bash
# Original (pode causar problemas)
wJalrXUt/K7MDENG+bPxRfi

# URL-encoded (recomendado)
wJalrXUt%2FK7MDENG%2BbPxRfi
```

### **3. MessageRejected Error**

**Causas comuns:**
- Endereço remetente não verificado no SES
- Conta em modo sandbox (só envia para emails verificados)
- Quota diária excedida

**Solução:**
```bash
# Verificar quota e emails verificados
php bin/console mautic:amazon-ses:debug --test-connection
```

### **4. Problemas de Email Remetente**

**Com detecção automática:**
```bash
# O plugin detecta automaticamente
php bin/console mautic:amazon-ses:debug --test-email=destino@domain.com

# Se não detectar, especifique manualmente
php bin/console mautic:amazon-ses:debug --test-email=destino@domain.com --from=remetente@domain.com
```

---

## 🔧 **Configurações Recomendadas**

### **Para ses+smtp (Máxima Compatibilidade):**
```
Esquema: ses+smtp
Host: email-smtp.us-east-1.amazonaws.com
Porta: 587 (STARTTLS - recomendado)
Criptografia: STARTTLS
Autenticação: Login
```

### **Para ses+api (Melhor Performance):**
```
Esquema: ses+api
Host: default
Porta: 465
Usuário: <aws-access-key>
Senha: <aws-secret-key>
Região: <aws-region>
```

### **Prioridade de Portas:**
1. **🥇 Porta 587 (STARTTLS)** - Melhor compatibilidade
2. **🥈 Porta 465 (SSL)** - Pode causar timeouts
3. **🥉 Porta 2587** - Alternativa de alguns provedores

---

## 📋 **Requisitos**

- **Mautic:** 6.0+
- **PHP:** 8.1+
- **Conta AWS SES** com domínio/email verificado
- **Symfony Amazon SES Bridge** (para esquema `ses+api`)
- **Extensões PHP:** curl, openssl, json

---

## 🧪 **Desenvolvimento**

### **Arquitetura do Plugin:**
- Event subscribers para processamento de webhooks
- Comandos de console para debugging
- Classes de serviço para manipulação de bounces/complaints
- Logging abrangente para troubleshooting
- Factory de transporte personalizado para SES

### **Testes:**
```bash
# Teste completo do ambiente
php bin/console mautic:amazon-ses:debug --test-connection --test-smtp-connectivity

# Teste de email com análise detalhada
php bin/console mautic:amazon-ses:debug --test-email=teste@domain.com --from=remetente@domain.com
```

---

## 📈 **Monitoramento e Logs**

O plugin registra eventos importantes:
- Emails enviados com sucesso
- Erros de conexão e credenciais
- Processamento de bounces/complaints
- Análises de debug detalhadas

**Localização dos logs:** `var/logs/` (pasta padrão do Mautic)

---

## 🆘 **Suporte**

### **Problemas Comuns:**
1. **Timeout de conexão** → Use `--test-smtp-connectivity`
2. **Email não detectado** → Configure `mailer_from_email` no Mautic
3. **Secret key inválida** → Use URL encoding para caracteres especiais
4. **Quota excedida** → Verifique limites com `--test-connection`

### **Debug Rápido:**
```bash
# Diagnóstico completo em um comando
php bin/console mautic:amazon-ses:debug --test-connection --test-smtp-connectivity --test-email=seu-email@domain.com
```

---

## 👥 **Créditos**

**🔧 Desenvolvimento Aprimorado:** Equipe de Desenvolvimento
**📝 Conceito Original:** Pablo Veintimilla

**Melhorias para Mautic 6:**
- Suporte a múltiplos esquemas de transporte
- Detecção automática de configurações
- Debug avançado com testes reais
- Compatibilidade aprimorada com provedores de hospedagem

---

<p align="center">
<strong>🚀 Plugin Amazon SES - Solução completa para envio de emails no Mautic 6</strong>
</p> 