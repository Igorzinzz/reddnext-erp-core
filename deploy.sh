#!/bin/bash

echo "🚀 Reddnext ERP - Gerador de Versão"
echo "----------------------------------"

# 1️⃣ Bloqueia se existir merge ou rebase em andamento
if [ -f .git/MERGE_HEAD ] || [ -d .git/rebase-apply ] || [ -d .git/rebase-merge ]; then
  echo "❌ Existe um merge ou rebase em andamento."
  exit 1
fi

# 2️⃣ Verifica se há alterações pendentes
if [[ -n $(git status --porcelain) ]]; then
  echo "❌ Existem alterações não commitadas."
  git status --short
  exit 1
fi

# 3️⃣ Lê versão do arquivo
RAW_VERSION=$(cat versao.txt | tr -d ' \n')

if [[ -z "$RAW_VERSION" ]]; then
  echo "❌ versao.txt está vazio"
  exit 1
fi

# 4️⃣ Normaliza versão (remove 'v' se existir)
VERSION="${RAW_VERSION#v}"
TAG="v$VERSION"

echo "📦 Versão detectada: $RAW_VERSION"
echo "🏷️ Tag normalizada: $TAG"

# 5️⃣ Verifica se a tag já existe
if git rev-parse "$TAG" >/dev/null 2>&1; then
  echo "❌ A tag $TAG já existe. Atualize a versão antes de continuar."
  exit 1
fi

# 6️⃣ Atualiza versao.txt (padronizado com v)
echo "$TAG" > versao.txt
git add versao.txt

# 7️⃣ Commit
git commit -m "Release $TAG" || {
  echo "❌ Falha ao criar commit"
  exit 1
}

# 8️⃣ Cria tag
git tag "$TAG" || {
  echo "❌ Falha ao criar tag $TAG"
  exit 1
}

# 9️⃣ Push
git push origin main || exit 1
git push origin "$TAG" || exit 1

echo "✅ Versão $TAG publicada com sucesso"
