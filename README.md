# Plugin Amazon SES para Mautic 6

<p align="center">
<img src="Assets/img/icon.png" alt="Amazon SES" width="200"/>
</p>

<p align="center">
<strong>Plugin oficial para integração do Mautic 6 com Amazon Simple Email Service (SES)</strong>
</p>

---

## 🚀 **Recursos**

- ✅ **Dual transport:** `ses+api` (recomendado) e `ses+smtp`
- ✅ **Auto-processamento:** bounces/complaints via SNS webhooks
- ✅ **Debug inteligente:** diagnóstico completo + detecção automática
- ✅ **Testes AWS:** conexão real + informações de quota

---

## 🔧 **Pré-requisitos AWS**

### 1. **Conta AWS + SES**
- Criar conta em [aws.amazon.com](https://aws.amazon.com)
- Ativar Amazon SES na região desejada

### 2. **IAM User**
- Criar usuário IAM + política `AmazonSESFullAccess`
- Gerar Access Key + Secret Key

### 3. **Verificação**
- Verificar domínio/email em SES → Verified identities
- Solicitar saída do sandbox para produção

---

## 📦 **Instalação**

```bash
# 1. Instalar via Composer
composer require rhafaman/mautic-amazon-ses-bundle

# 2. Limpar cache e ativar
php bin/console cache:clear
php bin/console mautic:plugins:reload

# 3. Verificar instalação
php bin/console mautic:amazon-ses:debug
```

---

## ⚙️ **Configuração**

**Local no Mautic:** Configuração → Configurações de Email → Transport

### 🥇 **SES API (RECOMENDADO)**

**DSN:** `ses+api://ACCESS_KEY:SECRET_KEY@default?region=REGIAO`

| Campo | Valor |
|-------|-------|
| Esquema | `ses+api` |
| Host | `default` |
| Usuário | `<aws-access-key>` |
| Senha | `<aws-secret-key>` |
| Região | `<aws-region>` |

### 🥈 **SES SMTP (Alternativo)**

**DSN:** `ses+smtp://ACCESS_KEY:SECRET_KEY@default:587?region=REGIAO`

| Campo | Valor |
|-------|-------|
| Esquema | `ses+smtp` |
| Host | `default` |
| Porta | `587` (recomendado) |
| Usuário | `<aws-access-key>` |
| Senha | `<aws-secret-key>` |
| Região | `<aws-region>` |

### 📝 **Credenciais AWS**

- **Access Key:** 20 caracteres (ex: `AKIA...`)
- **Secret Key:** 20-128 caracteres
- **Região:** `us-east-1`, `sa-east-1`, `eu-west-1`, etc.

### ⚠️ **IMPORTANTE: Secret Key**

**🚨 Para evitar problemas, regenere a Secret Key se contém `/` ou `+`**

Se não puder regenerar, use URL encoding:
```bash
# Caracteres problemáticos → URL encoded
/ → %2F
+ → %2B
= → %3D
```

### 💡 **Exemplos de DSN**

```bash
# SES API (recomendado)
ses+api://AKIAIOSFODNN7EXAMPLE:wJalrXUtnFEMI_K7MDENG_bPxRfiCYEXAMPLEKEY@default?region=us-east-1

# SES SMTP  
ses+smtp://AKIAIOSFODNN7EXAMPLE:wJalrXUtnFEMI_K7MDENG_bPxRfiCYEXAMPLEKEY@default:587?region=sa-east-1
```
**Nota:** Host `default` resolve automaticamente para endpoint AWS correto

---

## 🔍 **Debug e Testes**

```bash
# Análise completa
php bin/console mautic:amazon-ses:debug

# Teste conexão AWS + quota
php bin/console mautic:amazon-ses:debug --test-connection

# Teste SMTP
php bin/console mautic:amazon-ses:debug --test-smtp-connectivity

# Envio teste (detecta remetente)
php bin/console mautic:amazon-ses:debug --test-email=destino@dominio.com

# Remetente específico
php bin/console mautic:amazon-ses:debug --test-email=destino@dominio.com --from=remetente@dominio.com
```

**Auto-detecção remetente:** `mailer_from_email` → `webmaster_email` → DSN → fallback

---

## 📡 **Webhooks SNS (Opcional)**

**Para auto-processamento de bounces/complaints:**

### Setup AWS:
1. **SES → Configuration sets** → criar + vincular event destinations
2. **SNS → Topics** → criar tópicos + subscriptions HTTPS
3. **Endpoint:** `https://seu-mautic.com/mailer/callback`
4. **⚠️ Habilitar:** "Raw message delivery"

**Resultado:** Plugin confirma subscrições + remove bounces automaticamente

---

## 🚨 **Troubleshooting**

```bash
# Diagnóstico completo
php bin/console mautic:amazon-ses:debug --test-connection
```

| ❌ Problema | ✅ Solução |
|-------------|-----------|
| **Timeout SMTP** | Use `ses+api` ou porta 587 |
| **InvalidSignature** | Regenere Secret Key sem `/` ou `+` |
| **MessageRejected** | Verifique domínio verificado no SES |
| **Sem remetente** | Configure `mailer_from_email` no Mautic |

---

## 📋 **Requisitos**

- **Mautic:** 6.0+
- **PHP:** 8.1+
- **Conta AWS SES** com domínio/email verificado  
- **Dependências:** `symfony/amazon-mailer` (instalada automaticamente)

---

## 📈 **Logs**

**Registra em** `var/logs/mautic_*.log`:
- ✅ Emails enviados + Message-ID  
- ❌ Erros conexão/credenciais AWS
- 📨 Bounces/complaints processados
- 🔍 Debug + quota AWS

---

## 📝 **Versão**

**v1.1.4** | Mautic 6.0+ | PHP 8.1+ | Autor: Rhafaman

---

<p align="center">
<strong>🚀 Plugin Amazon SES - Solução completa para envio de emails no Mautic 6</strong>
</p> 