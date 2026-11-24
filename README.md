# Tutor LMS — Custom Single Lesson (TEAP)

Template customizado para a visualização de lições (*Single Lesson*) do plugin **Tutor LMS** no WordPress.

O layout opera em **dois modos**, decididos automaticamente por marcadores escritos na descrição do tópico:

* **Modo Simulado** — ambiente de prova cronometrada, com grade de questões e contador regressivo.
* **Modo Aula** — leitura limpa, sem cronômetro e sem grade, para aulas introdutórias, orientações, tutoriais e conteúdo de apoio.

Ambos os modos compartilham o mesmo header, o mesmo menu lateral, as mesmas ferramentas de anexos e anotações e o mesmo **Guia de Integração** (tour interativo).

---

## 🚀 Funcionalidades Principais

### Comuns aos dois modos

* **Tela Dividida (Split Screen):** quando o conteúdo contém o delimitador `<!--split-->`, a página se divide em duas colunas com rolagem independente. Sem o delimitador, o conteúdo ocupa uma coluna única centralizada.
* **Vídeo da Aula:** o vídeo configurado na aba *Vídeo* do editor do Tutor é renderizado no topo da coluna esquerda, em 16:9. Usa o player nativo do Tutor e, se ele não responder, recorre a um embed via oEmbed ou a uma tag `<video>`.
* **Abas Deslizantes Laterais:** gavetas de acesso rápido para **Dica** e **Resolução**, renderizadas apenas quando os respectivos delimitadores existem no conteúdo.
* **Pausa Automática de Mídia:** ao fechar uma gaveta, o script pausa vídeos nativos (`<video>`) e reinicia `iframe`s incorporados (YouTube/Vimeo), evitando áudio tocando em segundo plano.
* **Painel:** botão no header que leva ao dashboard do aluno no Tutor LMS (`tutor_dashboard_url()`; se a função não existir, usa `/dashboard/`).
* **Anexos:** painel lateral que lista os arquivos da aba *Anexos* da aula, com nome, tamanho e download direto. O botão exibe um badge com a contagem.
* **Anotações:** painel lateral com as anotações do aluno naquela aula, cada uma com data e opção de excluir.
* **Anotar:** modal ancorado no canto superior direito para escrever uma anotação. Salva por AJAX, sem recarregar a página, e atualiza a lista e o badge em tempo real.
* **Guia de Integração:** tour com overlay e tooltip que apresenta cada parte da tela. Ver a seção *Guia de Integração (Onboarding Tour)*.
* **Menu Lateral Híbrido:** ver a seção *Estrutura do Currículo*.

### Exclusivos do Modo Simulado

* **Grade de Questões:** barra numerada no header com todas as lições do tópico, coloridas por estado — azul (atual), verde (concluída), amarelo (em andamento), branco (não iniciada).
* **Cronômetro por Simulado:** contador regressivo de 90 minutos, independente para cada simulado e sincronizado entre as questões do mesmo tópico. Inclui **Pausar/Retomar** e **Reiniciar**. Ver a seção *Cronômetro*.
* **Rastreamento H5P via xAPI:** quando o aluno interage com uma questão H5P, o script recebe o evento `interacted` e marca aquela questão como "em andamento" na grade. Funciona com o H5P dentro de iframes. Ver a seção *Rastreamento H5P*.

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

Depois de subir uma nova versão do template, limpe o cache do site (plugin de cache ou CDN): o CSS e o JavaScript ficam dentro da página.

---

## ⚙️ Configuração Inicial

Abra `single-lesson.php` e ajuste os IDs dos cursos onde o layout deve ser aplicado:

```php
$custom_course_ids = array( 127272, 68267 ); // IDs dos cursos
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

## 🎚️ Os Marcadores `[simulado]` e `[orientacao]`

Os marcadores controlam o modo da lição **e** como o tópico aparece no menu lateral. Escreva-os na **descrição (summary) do Tópico**, no Course Builder do Tutor LMS. A detecção ignora caixa e espaços internos, então `[Simulado]` e `[ simulado ]` funcionam igual.

| | Tópico com `[simulado]` | Tópico com `[orientacao]` | Tópico sem marcador |
|---|---|---|---|
| Header | Grade de questões + cronômetro | Título da aula | Título da aula |
| Menu lateral | Agrupado por área | Agrupado por área | Tópico próprio, listando suas aulas |
| Botões de ação | Painel, Guia, Anexos, Anotações, Anotar, Concluir, navegação | Idênticos | Idênticos |
| Split `<!--split-->` | Funciona | Funciona | Funciona |

* **`[simulado]`** — para os tópicos de prova.
* **`[orientacao]`** — para conteúdos que ficam **entre os simulados** de uma área (ex.: *Orientações para os simulados*): entram no grupo da área, na posição em que o tópico está no curso, mas usam o layout de aula. Aceita também `[orientação]`, `[orientacoes]` e `[orientações]`.

Não use os dois marcadores no mesmo tópico: se houver `[simulado]`, vale o layout de simulado. O marcador `[simulado]` é removido automaticamente do texto exibido ao aluno.

---

## 📝 Como Estruturar o Conteúdo da Aula

A divisão de telas e as gavetas laterais são definidas por delimitadores HTML escritos direto no editor de conteúdo da aula (Gutenberg ou Clássico):

```text
[Texto e imagens que ficarão no lado esquerdo da tela]

<!--split-->

[Perguntas, atividades ou shortcode do H5P, no lado direito]

<!--tutorial-->

[Dica de apoio ou vídeo explicativo, para a gaveta "Dica"]

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

### Tópicos de simulado e de orientação — agrupados por área

Nomeie os tópicos no padrão `Área - Nome`, usando um traço como separador, e marque a descrição com `[simulado]` ou `[orientacao]`:

* **Tópico 1:** `Agrárias/Veterinárias - Simulado 1` → `[simulado]`
* **Tópico 2:** `Agrárias/Veterinárias - Simulado 2` → `[simulado]`
* **Tópico 3:** `Agrárias/Veterinárias - Orientações para os simulados` → `[orientacao]`
* **Tópico 4:** `Agrárias/Veterinárias - Simulado 3` → `[simulado]`

O que vem antes do traço vira o grupo expansível; o que vem depois vira o item, que leva à primeira lição daquele tópico.

```text
▼ Agrárias/Veterinárias
  ├─ ✓ Simulado 1                       → primeira questão do Tópico 1
  ├─   Simulado 2                       → primeira questão do Tópico 2
  ├─   Orientações para os simulados    → primeira aula do Tópico 3 (layout de aula)
  └─   Simulado 3                       → primeira questão do Tópico 4
```

O item ganha o ✓ verde quando **todas as lições do tópico** estão marcadas como concluídas.

A quebra do título normaliza espaços não-quebráveis (`&nbsp;`) e aceita qualquer variação de traço: hífen (`-`), meia-risca (`–`), travessão (`—`) ou sinal de menos (`−`).

### Tópicos de aula — listagem normal

Sem marcador, o tópico aparece com o nome inteiro e lista **as próprias aulas**, cada uma linkando para si mesma, com um ✓ verde nas já concluídas:

```text
▼ Intro Class
  ├─ ✓ Boas-Vindas ao Preparatório
  ├─ ✓ Instruções para o Desafio
  └─   Seus Objetivos
```

O grupo que contém a aula atual abre automaticamente ao carregar a página.

---

## ⏱️ Cronômetro

* **Um cronômetro por simulado:** cada tópico tem sua própria contagem, guardada em `localStorage` por timestamp. Passar de uma questão para outra do mesmo simulado não interrompe o tempo.
* **Só conta enquanto o aluno está no simulado:** ao sair para outro simulado, uma aula avulsa, outro site ou fechar o navegador, o tempo daquele simulado deixa de correr. Ao voltar, ele continua de onde parou.
* **Zerou:** o contador fica em `00:00` em vermelho, mas o simulado **não** é encerrado — serve para o aluno se programar.
* **Pausar / Reiniciar:** os botões ao lado do contador. Reiniciar pede confirmação.
* **Guia de Integração:** enquanto o modal de boas-vindas ou o tour estão abertos, o tempo fica parado (ver abaixo).

Chaves usadas no `localStorage`, por tópico:

```text
exam_end_time_topic_{topic_id}     fim previsto da prova (timestamp)
exam_paused_time_topic_{topic_id}  segundos restantes quando pausado
exam_tour_hold_topic_{topic_id}    pausa feita pelo Guia (não pelo aluno)
exam_last_seen_topic_{topic_id}    última vez que uma página do simulado esteve aberta
```

> Se o aluno deixar o simulado aberto em outra aba do navegador, ele continua "dentro" do simulado e o tempo segue contando.

---

## 🧭 Guia de Integração (Onboarding Tour)

Tour com fundo escurecido, destaque em volta de cada elemento e tooltip explicativo. Mostra, em ordem: Menu (o menu lateral abre sozinho), Questões, Nome e cronômetro, Anexos, Anotações, Anotar, Marcar como concluído, setas de navegação, as duas colunas, coluna da esquerda, coluna da direita, Resolução e Dica.

Passos cujo elemento não existe na aula são pulados automaticamente (ex.: numa aula sem `[simulado]` não há grade nem cronômetro). Navegação por botões, pelas setas do teclado e `Esc` para fechar.

### Configuração

No topo de `single-lesson.php`, seção **1.1**:

```php
// Aulas onde o guia abre SOZINHO (com o modal "Ver tutorial / Pular")
$tour_auto_lesson_ids = array( 127471, 131835 );

// Aulas onde o botão "Guia" do header NÃO aparece
$tour_button_exclude_ids = array( 77136, 72713 );

// true  = o modal automático aparece só na 1ª visita do aluno a cada aula
// false = o modal aparece em toda visita
$tour_auto_show_once = true;
```

* **Modal de boas-vindas:** só nas aulas de `$tour_auto_lesson_ids`. Pergunta se o aluno quer **Ver tutorial** ou **Pular**.
* **Botão "Guia":** no header de todas as aulas dos cursos configurados, exceto as de `$tour_button_exclude_ids`. Abre o tour direto, sem o modal.
* **Cronômetro:** em aulas com o modal, o tempo só começa depois de **Pular** ou de concluir/fechar o tour. Em qualquer aula, abrir o tour pelo botão **Guia** pausa o tempo, que volta ao fechar. Se o aluno já tinha pausado por conta própria, o tempo continua pausado depois do tour.

Os textos de cada passo ficam no array `tourSteps`, na seção **F** do JavaScript. Eles falam do preparatório TEAP; se outro curso usar o template, ajuste os textos ou exclua as aulas dele do botão.

---

## 📡 Rastreamento H5P

O H5P roda dentro de um iframe (`.new-h5p-iframe`), então o `window.H5P` da página não existe. O script procura o H5P dentro de cada iframe — inclusive iframes aninhados, até 3 níveis — e escuta o `xAPI` de lá. Ele tenta a cada segundo por até 30 segundos e de novo sempre que um iframe termina de carregar.

* **Iframe do mesmo domínio:** usa o evento `interacted` do xAPI.
* **Iframe de outro domínio:** o navegador bloqueia o acesso ao H5P; nesse caso, clicar dentro do iframe conta como interação.

Para conferir no site, abra o console do navegador (F12) numa questão e rode:

```js
document.querySelector('.new-h5p-iframe').getAttribute('data-teap-h5p')
```

`"tracked"` = xAPI ativo · `"click"` = usando o clique como sinal · `null` = H5P não encontrado.

O estado "em andamento" fica em `localStorage` (`lesson_status_{lesson_id}`) e aparece em amarelo na grade quando o aluno está em outra questão.

---

## 🗒️ Onde as Anotações Ficam Salvas

As anotações são gravadas em `user_meta`, uma chave por aula:

```text
_tutor_custom_notes_{lesson_id}  =>  array de [ 'text' => ..., 'date' => ... ]
```

Essa é uma implementação própria, independente do sistema de notas nativo do Tutor — que pertence à interface padrão do plugin, substituída por este template. O limite é de 200 anotações por aula, para não inflar a tabela de usermeta.

Os dois endpoints AJAX registrados são `tutor_custom_save_note` e `tutor_custom_delete_note`, ambos protegidos por nonce e exigindo usuário autenticado.

---

## 🌐 Compatibilidade de Navegadores

O CSS evita recursos recentes que quebram o layout em navegadores antigos. Um exemplo já corrigido: dividir uma medida por outra dentro de `calc()` (ex.: `calc(10px / 100vw)`) só funciona a partir do Chrome 140 e do Safari 26; em versões anteriores e no Firefox, os painéis laterais apareciam abertos do lado esquerdo. Ao alterar o CSS, prefira cálculos que multiplicam ou dividem por números simples.

---

## 🛠️ Tecnologias Utilizadas

* **PHP:** queries do WordPress/Tutor LMS, detecção de modo, handlers AJAX com nonce e sanitização.
* **CSS3:** Flexbox, Grid Layout, `aspect-ratio`, transições das gavetas e media queries próprias (sem dependência do Elementor).
* **JavaScript (Vanilla):** cronômetro por Unix timestamp, persistência em `localStorage`, `fetch` para as anotações, listeners xAPI do H5P (inclusive dentro de iframes), tour de integração e manipulação de DOM para gavetas, accordion e modais.

---

## 📄 Licença

Este projeto está sob a licença [MIT](LICENSE). Sinta-se livre para usar, modificar e distribuir conforme suas necessidades.
