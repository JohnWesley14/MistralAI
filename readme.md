# 🚀 Guia de Inicialização - Leitor Inteligente de PDF com IA

Aplicação web de alto desempenho para análise automatizada de documentos PDF em lote utilizando Inteligência Artificial local (Ollama) e extração nativa de texto de alta velocidade (Poppler/XPDF).

---

## 📋 1. Pré-requisitos do Sistema

- **Servidor Web:** Apache 2.4+ (ex: XAMPP, WAMP ou nativo)
- **Linguagem:** PHP 8.1+ (extensões `pdo_mysql`, `curl`, `mbstring` ativas)
- **Gerenciador de Pacotes:** [Composer](https://getcomposer.org/)
- **Banco de Dados:** MySQL 5.7+ / MariaDB
- **Motor de IA Local:** [Ollama](https://ollama.com/)
- **Extrator de PDF:** [Poppler para Windows](https://github.com/oschwartz10612/poppler-windows/releases)

---

## 🛠️ 2. Passo a Passo de Instalação

### 2.1. Dependências do PHP
Na pasta raiz do projeto, execute no terminal:

```bash
composer install