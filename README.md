# Tutor LMS - Custom Single Lesson (Exam Simulation Mode)

Este repositório contém um template customizado para a visualização de lições (*Single Lesson*) do plugin **Tutor LMS** para WordPress. O layout foi desenvolvido para simular um ambiente de prova ou avaliação cronometrada, dividindo a tela entre leitura de textos e resolução de atividades, com recursos avançados de persistência de tempo, controle de mídia e rastreamento de progresso de atividades interativas H5P.

---

## 🚀 Funcionalidades Principais

* **Tela Dividida (Split Screen):** Organiza a página em duas colunas independentes com rolagem de tela individual. O lado esquerdo é dedicado à leitura do material (texto/imagens) e o lado direito é reservado para as questões ou atividades.
* **Cronômetro de Prova Persistente:** Contador decrescente de 50 minutos que permanece ativo e sincronizado entre as páginas do mesmo tópico. Utiliza persistência via `localStorage` com cálculo de tempo real (timestamp), impedindo que o aluno trapaceie reiniciando a página ou fechando o navegador. Inclui botões para **Pausar/Retomar** e **Reiniciar** o tempo.
* **Abas Deslizantes Laterais (Slide-out Drawers):** Duas gavetas laterais de acesso rápido para exibir **Tutorial** (dicas) e **Resolução** de forma limpa. 
* **Pausa Automática de Vídeo/Áudio:** Se o aluno fechar o painel lateral de Tutorial ou Resolução enquanto assiste a um vídeo, o script identifica e pausa automaticamente a reprodução de mídias nativas (`<video>`) ou incorporadas em `iframe` (YouTube/Vimeo).
* **Menu Lateral Agrupado por Áreas (Dropdowns):** Reestrutura o menu padrão do Tutor LMS. Em vez de listar tópicos como menus independentes, ele agrupa tópicos que possuem prefixos em comum (ex: `Área - Tópico`) sob uma mesma categoria expansível. O clique no subitem direciona automaticamente o aluno para a primeira aula daquele conjunto, sem quebrar o loop interno do WordPress.
* **Rastreamento H5P via xAPI:** Monitora interações em tempo real com atividades H5P. No primeiro clique do aluno em uma questão, o script escuta o evento xAPI `'interacted'` e altera visualmente a cor da aula na barra superior para amarelo ("Em andamento").

---

## 📂 Estrutura de Instalação

Para utilizar este template no seu site WordPress, você deve adicioná-lo ao diretório do seu **tema filho (child theme)** para evitar que atualizações futuras do plugin apaguem suas customizações.

Crie ou envie o arquivo para o seguinte caminho:

```text
wp-content/themes/seu-tema-child/tutor/single-lesson.php
```

---

## ⚙️ Configuração Inicial

Abra o arquivo `single-lesson.php` e altere a constante correspondente ao ID do curso que deseja aplicar o comportamento. Na linha **23**, substitua o valor pelo ID do seu curso:

```php
// Definir o ID do curso específico para aplicar o comportamento
$custom_course_id = 92300; // Substitua por seu ID de curso real
```

*Nota: Todas as demais aulas de outros cursos que não correspondam a este ID continuarão carregando o layout padrão e nativo do Tutor LMS.*

---

## 📝 Como Estruturar o Conteúdo no WordPress

Toda a lógica de divisão de telas e carregamento das abas laterais é feita de forma simples diretamente no **Editor de Blocos (Gutenberg)** ou **Editor Clássico** do WordPress, utilizando delimitadores HTML.

Escreva o conteúdo da sua lição em um único campo de texto estruturado da seguinte forma:

```text
[Insira aqui o texto e imagens explicativas que ficarão no lado esquerdo da tela]

<!--split-->

[Insira aqui os blocos de perguntas, atividades ou shortcode do H5P que ficarão no lado direito]

<!--tutorial-->

[Insira aqui a dica de apoio ou bloco de vídeo explicativo do YouTube/Vimeo que aparecerá na gaveta de "Tutorial"]

<!--resolution-->

[Insira aqui o texto passo a passo ou o player de vídeo com a resolução comentada para a gaveta "Resolução"]
```

> **Atenção:** Caso não utilize os delimitadores `<!--tutorial-->` ou `<!--resolution-->` em uma lição específica, as respectivas abas flutuantes no canto da tela não serão renderizadas de forma automática.

---

## 🗂️ Estrutura do Currículo no Tutor LMS

Para que o menu dinâmico de navegação lateral agrupe os tópicos por área sob o mesmo dropdown expansível (accordion), siga o padrão de nomenclatura no cadastro de **Tópicos** do Tutor LMS usando traços:

* **Tópico 1:** `Biológicas/Saúde - Texto 1`
* **Tópico 2:** `Biológicas/Saúde - Texto 2`
* **Tópico 3:** `Exatas/Tecnológicas - Texto 1`
* **Tópico 4:** `Exatas/Tecnológicas - Texto 2`

**Resultado no menu gerado:**
```text
▼ Biológicas/Saúde
  ├─ Texto 1 (Link para primeira aula do Tópico 1)
  └─ Texto 2 (Link para primeira aula do Tópico 2)
  
▼ Exatas/Tecnológicas
  ├─ Texto 1 (Link para primeira aula do Tópico 3)
  └─ Texto 2 (Link para primeira aula do Tópico 4)
```

O código foi projetado para normalizar espaços em branco não-quebráveis (`&nbsp;`) e realizar a quebra de texto de forma segura utilizando qualquer variação de hífen padrão (`-`), meia-risca (`–`), travessão (`—`) ou sinal matemático de menos (`−`).

---

## 🛠️ Tecnologias Utilizadas

* **PHP:** Estruturação dinâmica e queries WordPress/Tutor LMS.
* **CSS3:** Flexbox, Grid Layout, transições de animação de abas e estilização responsiva.
* **JavaScript (Vanilla):** Lógica do cronômetro baseado em Unix timestamp, persistência em `localStorage`, listeners de eventos xAPI para H5P e manipulação de DOM para drawers e accordions.

---

## 📄 Licença

Este projeto está sob a licença [MIT](LICENSE). Sinta-se livre para usar, modificar e distribuir conforme suas necessidades.
