#!/bin/bash

echo "🚀 Reddnext ERP - Gerador de Versão"
echo "----------------------------------"

# Verifica se está dentro do repositório correto
if [ ! -d ".git" ]; then
  echo "❌ Erro: este script deve ser executado dentro do erp-core"
  exit 1
fi

# Verifica se há alterações
if git diff --quiet && git diff --cached --quiet; then
  echo "⚠️ Nenhuma alteração detectada. Nada para versionar."
  exit 1
fi

# Pergunta a versão
read -p "Digite a versão (ex: v2.8): " VERSAO

if [ -z "$VERSAO" ]; then
  echo "❌ Versão inválida."
  exit 1
fi

# Verifica se a tag já existe
if git tag | grep -q "^$VERSAO$"; then
  echo "❌ A tag $VERSAO já existe."
  exit 1
fi

# Commit
git add .
git commit -m "Release $VERSAO"

# Tag
git tag $VERSAO

# Push
git push origin main
git push origin $VERSAO

echo ""
echo "✅ Versão $VERSAO enviada com sucesso!"
echo "🌐 Deploy disponível via deploy.php"
