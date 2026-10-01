# Tutor LMS — Custom Single Lesson (TEAP)

Template customizado para a visualização de lições (*Single Lesson*) do plugin **Tutor LMS** no WordPress.

O layout opera em **dois modos**, decididos automaticamente por um marcador escrito na descrição do tópico:

* **Modo Simulado** — ambiente de prova cronometrada, com grade de questões e contador regressivo.
* **Modo Aula** — leitura limpa, sem cronômetro e sem grade, para aulas introdutórias, tutoriais e conteúdo de apoio.

Ambos os modos compartilham o mesmo header, o mesmo menu lateral e as mesmas ferramentas de anexos e anotações.

---

## 🚀 Funcionalidades Principais

### Comuns aos dois modos

* **Tela Dividida (Split Screen):** quando o conteúdo contém o delimitador `<!--split-->`, a página se divide em duas colunas com rolagem independente. Sem o delimitador, o conteúdo ocupa uma coluna única centralizada.
* **Vídeo da Aula:** o vídeo configurado na aba *Vídeo* do editor do Tutor é renderizado no topo da coluna esquerda, em 16:9. Usa o player nativo do Tutor e, se ele não responder, recorre a um embed via oEmbed ou a uma tag `<video>`.
* **Abas Deslizantes Laterais:** gavetas de acesso rápido para **Tutorial** (dicas) e **Resolução**, renderizadas apenas quando os respectivos delimitadores existem no conteúdo.
* **Pausa Automática de Mídia:** ao fechar uma gaveta, o script pausa vídeos nativos (`<video>`) e reinicia `iframe`s incorporados (YouTube/Vimeo), evitando áudio tocando em segundo plano.
* **Anexos:** painel lateral que lista os arquivos da aba *Anexos* da aula, com nome, tamanho e download direto. O botão exibe um badge com a contagem.
* **Anotações:** painel lateral com as anotações do aluno naquela aula, cada uma com data e opção de excluir.
* **Anotar:** modal ancorado no canto superior direito para escrever uma anotação. Salva por AJAX, sem recarregar a página, e atualiza a lista e o badge em tempo real.
* **Menu Lateral Híbrido:** ver a seção *Estrutura do Currículo*.

### Exclusivos do Modo Simulado

* **Grade de Questões:** barra numerada no header com todas as lições do tópico, coloridas por estado — azul (atual), verde (concluída), amarelo (em andamento), branco (não iniciada).
* **Cronômetro Persistente:** contador regressivo de 90 minutos, sincronizado entre as páginas do mesmo tópico. Usa `localStorage` com cálculo por timestamp, de modo que recarregar ou fechar o navegador não devolve tempo ao aluno. Inclui **Pausar/Retomar** e **Reiniciar**.
* **Rastreamento H5P via xAPI:** no primeiro clique do aluno em uma questão H5P, o script escuta o evento `interacted` e marca aquela questão como "em andamento" na grade.

---

## 📂 Estrutura de Instalação

São **dois arquivos**. Ambos vão no **tema filho**, para que atualizações do plugin não apaguem as customizações.

**1. O template:**

```text
wp-content/themes/seu-tema-child/tutor/single-lesson.php
```

**2. Os handlers das anotações** — cole o conteúdo de `anotacoes-functions.php` no final do `functions.php` do tema filho (sem repetir a tag `<?php` de abertura):

```text
wp-content/themes/seu-tema-child/functions.php
```

> **Atenção:** sem o passo 2, o botão **Anotar** abre o modal, mas o botão Salvar falha. Os endpoints AJAX precisam estar registrados no WordPress.

---

## ⚙️ Configuração Inicial

Abra `single-lesson.php` e ajuste o ID do curso onde o layout deve ser aplicado:

```php
// Definir o ID do curso específico para aplicar o comportamento
$custom_course_id = 127272; // Substitua pelo ID do seu curso
```

Lições de qualquer outro curso continuam carregando o layout padrão do Tutor LMS.

### Duração da prova

O cronômetro é definido no bloco JavaScript, dentro da seção **A**:

```js
var durationMinutes = 90;
```

### Header do tema

O template chama `get_tutor_header( true )` e `get_tutor_footer( true )`. O `true` ativa o modo sem distrações do Tutor, que suprime o header e o footer do tema — incluindo o do **Elementor Theme Builder** — mantendo `wp_head()` e `wp_footer()` intactos para que scripts e estilos continuem carregando.

---

## 🎚️ O Marcador `[simulado]`

Um único marcador controla o modo da lição **e** como o tópico aparece no menu lateral.

Escreva `[simulado]` na **descrição (summary) do Tópico**, no Course Builder do Tutor LMS. A detecção ignora caixa e espaços internos, então `[Simulado]` e `[ simulado ]` funcionam igual.

| | Tópico **com** `[simulado]` | Tópico **sem** o marcador |
|---|---|---|
| Header | Grade de questões + cronômetro | Título da aula |
| Menu lateral | Agrupado por área (ver abaixo) | Tópico próprio, listando suas aulas |
| Botões de ação | Anexos, Anotações, Anotar, Concluir, navegação | Idênticos |
| Split `<!--split-->` | Funciona | Funciona |

O marcador é removido automaticamente do texto exibido ao aluno.

---

## 📝 Como Estruturar o Conteúdo da Aula

A divisão de telas e as gavetas laterais são definidas por delimitadores HTML escritos direto no editor de conteúdo da aula (Gutenberg ou Clássico):

```text
[Texto e imagens que ficarão no lado esquerdo da tela]

<!--split-->

[Perguntas, atividades ou shortcode do H5P, no lado direito]

<!--tutorial-->

[Dica de apoio ou vídeo explicativo, para a gaveta "Tutorial"]

<!--resolution-->

[Passo a passo ou vídeo com a resolução comentada, para a gaveta "Resolução"]
```

Todos os delimitadores são opcionais e independentes. Sem `<!--split-->`, a aula fica em coluna única; sem `<!--tutorial-->` ou `<!--resolution-->`, as respectivas gavetas não são renderizadas.

### URLs de vídeo nas gavetas

Para que uma URL do YouTube ou Vimeo vire um player dentro da gaveta, escreva-a **em uma linha própria**, separada do delimitador:

```text
<!--resolution-->
https://www.youtube.com/watch?v=XXXXXXXXXXX
```

O template também converte URLs coladas logo após o delimitador, mas a linha separada é o formato confiável, porque é o que o auto-embed nativo do WordPress reconhece.

---

## 🗂️ Estrutura do Currículo no Tutor LMS

O menu lateral monta cada tópico de um jeito, conforme o marcador.

### Tópicos de simulado — agrupados por área

Nomeie os tópicos no padrão `Área - Nome`, usando um traço como separador, e marque a descrição com `[simulado]`:

* **Tópico 1:** `Biológicas/Saúde - Texto 1`
* **Tópico 2:** `Biológicas/Saúde - Texto 2`
* **Tópico 3:** `Exatas/Tecnológicas - Texto 1`

O que vem antes do traço vira o grupo expansível; o que vem depois vira o item, que leva à primeira questão daquele tópico.

```text
▼ Biológicas/Saúde
  ├─ Texto 1 → primeira questão do Tópico 1
  └─ Texto 2 → primeira questão do Tópico 2

▼ Exatas/Tecnológicas
  └─ Texto 1 → primeira questão do Tópico 3
```

A quebra do título normaliza espaços não-quebráveis (`&nbsp;`) e aceita qualquer variação de traço: hífen (`-`), meia-risca (`–`), travessão (`—`) ou sinal de menos (`−`).

### Tópicos de aula — listagem normal

Sem o marcador, o tópico aparece com o nome inteiro e lista **as próprias aulas**, cada uma linkando para si mesma, com um ✓ verde nas já concluídas:

```text
▼ Intro Class
  ├─ ✓ Boas-Vindas ao Preparatório
  ├─ ✓ Instruções para o Desafio
  └─   Seus Objetivos
```

O grupo que contém a aula atual abre automaticamente ao carregar a página.

---

## 🗒️ Onde as Anotações Ficam Salvas

As anotações são gravadas em `user_meta`, uma chave por aula:

```text
_tutor_custom_notes_{lesson_id}  =>  array de [ 'text' => ..., 'date' => ... ]
```

Essa é uma implementação própria, independente do sistema de notas nativo do Tutor — que pertence à interface padrão do plugin, substituída por este template. O limite é de 200 anotações por aula, para não inflar a tabela de usermeta.

Os dois endpoints AJAX registrados são `tutor_custom_save_note` e `tutor_custom_delete_note`, ambos protegidos por nonce e exigindo usuário autenticado.

---

## 🛠️ Tecnologias Utilizadas

* **PHP:** queries do WordPress/Tutor LMS, detecção de modo, handlers AJAX com nonce e sanitização.
* **CSS3:** Flexbox, Grid Layout, `aspect-ratio`, transições das gavetas e media queries próprias (sem dependência do Elementor).
* **JavaScript (Vanilla):** cronômetro por Unix timestamp, persistência em `localStorage`, `fetch` para as anotações, listeners xAPI do H5P e manipulação de DOM para gavetas, accordion e modal.

---

## 📄 Licença

Este projeto está sob a licença [MIT](LICENSE). Sinta-se livre para usar, modificar e distribuir conforme suas necessidades.
