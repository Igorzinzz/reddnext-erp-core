#!/bin/bash

echo "🚀 Reddnext ERP - Gerador de Versão"
echo "----------------------------------"

# 1️⃣ Lê versão
RAW_VERSION=$(cat versao.txt | tr -d ' \n')

if [[ -z "$RAW_VERSION" ]]; then
  echo "❌ versao.txt está vazio"
  exit 1
fi

# 2️⃣ Normaliza versão
VERSION="${RAW_VERSION#v}"
TAG="v$VERSION"

echo "📦 Versão: $TAG"

# 3️⃣ Commit de tudo que mudou
git add -A

git commit -m "Release $TAG" || {
  echo "❌ Nada para versionar"
  exit 1
}

# 4️⃣ Cria tag
git tag "$TAG" || {
  echo "❌ Falha ao criar tag $TAG"
  exit 1
}

# 5️⃣ Push
git push origin main || exit 1
git push origin "$TAG" || exit 1

echo "✅ Versão $TAG publicada com sucesso"
