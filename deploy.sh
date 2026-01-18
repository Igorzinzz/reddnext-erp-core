#!/bin/bash

echo "🚀 Reddnext ERP - Gerador de Versão"
echo "----------------------------------"

# Verifica se há merge ou rebase pendente
if [ -f .git/MERGE_HEAD ] || [ -d .git/rebase-apply ] || [ -d .git/rebase-merge>
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
