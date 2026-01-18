#!/bin/bash

echo "🚀 Reddnext ERP - Gerador de Versão"
echo "----------------------------------"

# Verifica se há merge ou rebase pendente
if [ -f .git/MERGE_HEAD ] || [ -d .git/rebase-apply ] || [ -d .git/rebase-merge ]; then
  echo "❌ Existe um merge ou rebase pendente. Resolva antes de versionar."
  exit 1
fi

# Verifica se há alterações não commitadas
if ! git diff-index --quiet HEAD --; then
  echo "❌ Existem alterações não commitadas."
  git status --short
  exit 1
fi

# Lê versão atual
VERSION_FILE="versao.txt"

if [ ! -f "$VERSION_FILE" ]; then
  echo "1.0" > $VERSION_FILE
fi

VERSION=$(cat $VERSION_FILE)
MAJOR=$(echo $VERSION | cut -d. -f1)
MINOR=$(echo $VERSION | cut -d. -f2)

NEW_VERSION="$MAJOR.$((MINOR + 1))"

echo $NEW_VERSION > $VERSION_FILE

git add $VERSION_FILE
git commit -m "Release v$NEW_VERSION"
git tag "v$NEW_VERSION"

git push origin main
git push origin "v$NEW_VERSION"

echo "✅ Versão v$NEW_VERSION publicada com sucesso"
