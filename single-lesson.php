<?php
/**
 * Custom Single Lesson Template - Versão Corrigida à Prova de Falhas (Encoding Safe)
 * 
 * @package Tutor\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $post;
$content_id  = tutor_utils()->get_post_id();
$course_id   = tutor_utils()->get_course_id_by_subcontent( $content_id );
$user_id     = get_current_user_id();
$current_user = wp_get_current_user();

// ==========================================================================
// 1. FILTRO DE CURSO ESPECÍFICO
// ==========================================================================
$custom_course_id = 92300; // <-- ALTERE AQUI PARA O ID DO SEU CURSO

if ( $course_id != $custom_course_id ) {
    include tutor()->path . 'templates/single-lesson.php';
    return;
}

// ==========================================================================
// FUNÇÃO AUXILIAR DE DIVISÃO DE TÍTULOS (PADRONIZAÇÃO À PROVA DE FALHAS)
// ==========================================================================
if ( ! function_exists( 'tutor_custom_split_topic_title' ) ) {
    function tutor_custom_split_topic_title( $title ) {
        // 1. Remove quebras de linha e espaços invisíveis (Word/Docs/etc)
        $clean_title = str_replace(
            array( "\xc2\xa0", "\xa0", "&nbsp;", "\r", "\n" ),
            ' ',
            $title
        );

        // 2. Transforma entidades HTML (se houver) em caracteres reais antes da checagem
        $clean_title = html_entity_decode( $clean_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        // 3. A REGEX DEFINITIVA:
        // \s* -> Zero ou mais espaços
        // \p{Pd}  -> QUALQUER caractere da categoria Unicode "Dash" (hífen, meia-risca, travessão, etc.)
        // \s* -> Zero ou mais espaços
        // Modificador /u habilitado para processar propriedades Unicode
        $regex_pattern = '/\s*\p{Pd}\s*/u';

        // Divide o título na primeira ocorrência do traço
        $parts = preg_split( $regex_pattern, $clean_title, 2 );

        // Se conseguiu dividir em 2 partes com sucesso
        if ( is_array( $parts) && count( $parts ) === 2 ) {
            return array(
                'area' => trim( $parts[0] ),
                'text' => trim( $parts[1] )
            );
        }

        // Retorno de segurança caso a string não tenha nenhum traço
        return array(
            'area' => trim( $title ),
            'text' => trim( $title )
        );
    }
}

// ==========================================================================
// 2. CONFIGURAÇÕES DE NAVEGAÇÃO E RECURSOS
// ==========================================================================
$contents    = tutor_utils()->get_course_prev_next_contents_by_id( $content_id );
$previous_id = $contents->previous_id;
$next_id     = $contents->next_id;
$topic_id    = $post->post_parent;

// Divisão dinâmica do conteúdo por delimitadores
$raw_content = apply_filters( 'the_content', $post->post_content );

$left_content       = '';
$right_content      = '';
$tutorial_content   = '';
$resolution_content = '';

$parts_resolution = explode( '<!--resolution-->', $raw_content );
$main_and_tutorial = $parts_resolution[0];
$resolution_content = isset( $parts_resolution[1] ) ? trim( $parts_resolution[1] ) : '';

$parts_tutorial = explode( '<!--tutorial-->', $main_and_tutorial );
$main_split = $parts_tutorial[0];
$tutorial_content = isset( $parts_tutorial[1] ) ? trim( $parts_tutorial[1] ) : '';

$parts_main = explode( '<!--split-->', $main_split );
$left_content  = isset( $parts_main[0] ) ? trim( $parts_main[0] ) : '';
$right_content = isset( $parts_main[1] ) ? trim( $parts_main[1] ) : '';

$lessons_query = new WP_Query( array(
    'post_type'      => 'lesson',
    'post_parent'    => $topic_id,
    'posts_per_page' => -1,
    'orderby'        => 'menu_order',
    'order'          => 'ASC'
) );

get_tutor_header();
?>

<!-- ==========================================================================
     3. FOLHA DE ESTILOS CSS
     ========================================================================== -->
<style>
/* Layout Estrutural */
.custom-course-container {
    display: flex;
    flex-direction: column;
    height: 100vh;
    font-family: sans-serif;
    background-color: #ffffff;
}

/* Header */
.custom-course-header {
    background-color: #1a1e68;
    color: #ffffff;
    padding: 15px 30px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    border-bottom: 2px solid #000;
}

/* Botão de Toggle do Menu Lateral */
.sidebar-toggle-btn {
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #ffffff;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
    transition: background 0.2s, border-color 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-right: 1rem;
}

.sidebar-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: #ffffff;
}

.lessons-grid-wrapper {
    display: flex;
    flex-direction: column;
    gap: 5px;
    max-width: 100%;
}

.lessons-grid-title {
    font-size: 13px;
    opacity: 0.8;
}

.lessons-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    max-width: 100%;
}

.lesson-num-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 35px;
    height: 35px;
    background-color: #ffffff;
    color: #333;
    text-decoration: none;
    font-weight: bold;
    border-radius: 4px;
    font-size: 14px;
    transition: opacity 0.2s;
}

.lesson-num-btn:hover {
    opacity: 0.9;
}

.lesson-num-btn.active {
    background-color: #0066ff !important;
    color: #ffffff !important;
}
.lesson-num-btn.completed {
    background-color: #1cbd55 !important;
    color: #ffffff !important;
}
.lesson-num-btn.in-progress {
    background-color: #ffcc00 !important;
    color: #000000 !important;
}

/* Metadata e Cronômetro */
.header-right-meta {
    display: flex;
    align-items: center;
    gap: 40px;
}

.student-info {
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.student-name {
    display: block;
    font-size: 18px;
    font-weight: 300;
}

.timer-container {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 5px;
}

.timer {
    font-size: 44px;
    font-weight: bold;
    line-height: 1;
}

.timer-controls {
    display: flex;
    gap: 5px;
}

.timer-ctrl-btn {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    color: #ffffff;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 11px;
    transition: background 0.2s;
}

.timer-ctrl-btn:hover {
    background: rgba(255, 255, 255, 0.3);
}

.timer-ctrl-btn.is-paused {
    background: #ffcc00;
    color: #000;
    animation: blinkPause 1s infinite alternate;
}

@keyframes blinkPause {
    0% { opacity: 1; }
    100% { opacity: 0.6; }
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 20px;
}

.finish-btn-wrapper .tutor-btn {
    background-color: #ffffff !important;
    color: #1a1e68 !important;
    border-radius: 20px !important;
    padding: 10px 25px !important;
    font-weight: bold !important;
    border: none !important;
}

.nav-arrows {
    display: flex;
    gap: 10px;
}

.arrow-btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 40px;
    height: 40px;
    border: 2px solid #fff;
    border-radius: 50%;
    color: #fff;
    text-decoration: none;
    font-weight: bold;
    font-size: 20px;
}

.arrow-btn.disabled {
    opacity: 0.3;
    pointer-events: none;
}

/* Área de Trabalho Central */
.custom-course-main-wrapper {
    display: flex;
    flex: 1;
    overflow: hidden;
    position: relative;
}

/* Menu Lateral Retrátil */
.custom-course-sidebar {
    width: 290px;
    background-color: #f4f6f9;
    border-right: 1px solid #e1e6eb;
    padding: 10px;
    overflow-y: auto;
    transition: width 0.3s cubic-bezier(0.16, 1, 0.3, 1), 
                padding 0.3s cubic-bezier(0.16, 1, 0.3, 1), 
                opacity 0.2s ease;
    opacity: 1;
}

/* Estado Fechado do Menu Lateral */
.custom-course-main-wrapper.sidebar-closed .custom-course-sidebar {
    width: 0px;
    padding-left: 0px;
    padding-right: 0px;
    opacity: 0;
    pointer-events: none;
    border-right: none;
}

.sidebar-title {
    font-size: 16px;
    margin-bottom: 20px;
    color: #1a1e68;
    font-weight: bold;
    white-space: nowrap;
}

.grouped-menu-accordion {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 250px;
}

.menu-group {
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid #e1e6eb;
    background: #ffffff;
}

.menu-group-header {
    width: 100%;
    background: #ffffff;
    border: none;
    padding: 12px 15px;
    text-align: left;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    font-weight: bold;
    color: #333;
    transition: background 0.2s;
}

.menu-group-header:hover {
    background: #edf0f5;
}

.group-arrow {
    font-size: 10px;
    transition: transform 0.2s;
}

.menu-group.is-open .group-arrow {
    transform: rotate(180deg);
}

.menu-group-items {
    list-style: none;
    margin: 0;
    padding: 0;
    display: none;
    border-top: 1px solid #e1e6eb;
    background-color: #fcfdfe;
}

.menu-group.is-open .menu-group-items {
    display: block;
}

.menu-item-link {
    border-bottom: 1px solid #f0f2f5;
}

.menu-item-link:last-child {
    border-bottom: none;
}

.menu-item-link a {
    display: block;
    padding: 11px 20px;
    color: #555;
    text-decoration: none;
    font-size: 13.5px;
    line-height: 1.4;
}

.menu-item-link a:hover {
    background-color: #edf2fc;
    color: #0066ff;
}

.menu-item-link.is-active {
    background-color: #e4ecfa;
    border-left: 4px solid #0066ff;
}

.menu-item-link.is-active a {
    font-weight: bold;
    color: #0066ff;
}

/* Divisor de tela (Split) */
.custom-course-body {
    display: flex;
    flex: 1;
    overflow: hidden;
    height: 100%;
}

.body-pane {
    flex: 1;
    overflow-y: auto;
    padding: 30px;
    height: 100%;
}

.pane-left {
    background-color: #ffffff;
}

.pane-right {
    background-color: #f8f9fa;
}

.pane-content {
    max-width: 100%;
}

.pane-divider {
    width: 2px;
    background-color: #1a1e68;
    align-self: stretch;
}

/* Gatilhos de abas laterais */
.slideout-triggers-container {
    position: fixed;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    flex-direction: column;
    gap: 10px;
    z-index: 1000;
}

.slideout-tab-btn {
    background-color: #1a1e68;
    color: #fff;
    border: 1px solid #ffffff;
    border-right: none;
    padding: 12px 16px;
    border-radius: 0 8px 8px 0;
    cursor: pointer;
    font-weight: bold;
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    transition: background 0.2s;
}

.slideout-tab-btn:hover {
    background-color: #0066ff;
}

/* Painéis deslizantes */
.slideout-panel {
    --panel-width-px: calc(50vh * 0.85 * 16 / 9);
    --panel-width-percent: calc((var(--panel-width-px) / 100vw) * 100%);
    position: fixed;
    right: calc(-1 * var(--panel-width-percent)); 
    width: var(--panel-width-percent);
    top: 25%;
    height: 50%;
    background-color: #ffffff;
    box-shadow: -5px 0 25px rgba(0, 0, 0, 0.15);
    transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 9999;
    display: flex;
    flex-direction: column;
	border-radius: 25px 0 0 25px;
    overflow: hidden;
}

.slideout-panel.is-open {
    right: 0;
}

.panel-header {
    background-color: #1a1e68;
    color: #fff;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 15%;
}

.panel-header h4 {
    margin: 0;
    font-size: 18px;
	color: #fff;
}

.panel-close-btn {
    background: none;
    border: none;
    color: #fff;
    font-size: 28px;
    cursor: pointer;
    line-height: 1;
}

.panel-body {
    color: #333;
    aspect-ratio: 16 / 9;
    height: 85%;
}

.panel-body iframe, 
.panel-body video {
    height: auto;
    width: 100%;
    aspect-ratio: 16 / 9;
}

/* Responsividade */
@media (max-width: 992px) {
    .custom-course-main-wrapper {
        flex-direction: column;
    }
    .custom-course-sidebar {
        width: 100% !important;
        border-right: none;
        border-bottom: 1px solid #e1e6eb;
        max-height: 250px;
    }
    .custom-course-main-wrapper.sidebar-closed .custom-course-sidebar {
        height: 0px !important;
        max-height: 0px !important;
        padding-top: 0px;
        padding-bottom: 0px;
    }
    .custom-course-body {
        height: auto;
    }
}

@media (max-width: 768px) {
    .custom-course-header {
        padding: 15px;
    }
    .lesson-num-btn {
        width: 30px !important;
        height: 30px !important;
        font-size: 12px !important;
    }
    .timer {
        font-size: 30px;
    }
}
</style>

<!-- ==========================================================================
     4. ESTRUTURA VISUAL HTML
     ========================================================================== -->
<div class="custom-course-container" 
     data-course-id="<?php echo esc_attr($course_id); ?>" 
     data-topic-id="<?php echo esc_attr($topic_id); ?>" 
     data-current-lesson-id="<?php echo esc_attr($content_id); ?>">
    
    <!-- HEADER -->
    <header class="custom-course-header">
        <div style="display: flex">
            <!-- Botão Menu Retrátil -->
            <button id="btn-toggle-sidebar" class="sidebar-toggle-btn" title="Mostrar/Ocultar Menu Lateral">
                <span>&#9776; Menu</span>
            </button>

            <!-- Questões -->
            <div class="lessons-grid-wrapper">
                <span class="lessons-grid-title">Questões:</span>
                <div class="lessons-grid">
                    <?php 
                    if ( $lessons_query->have_posts() ) : 
                        $count = 1;
                        while ( $lessons_query->have_posts() ) : $lessons_query->the_post();
                            $loop_lesson_id = get_the_ID();
                            $is_current = ( $loop_lesson_id === $content_id );
                            $is_completed = tutor_utils()->is_completed_lesson( $loop_lesson_id, $user_id );
                            
                            $class = 'lesson-num-btn';
                            if ( $is_current ) {
                                $class .= ' active';
                            } elseif ( $is_completed ) {
                                $class .= ' completed';
                            }
                            ?>
                            <a href="<?php the_permalink(); ?>" 
                            class="<?php echo esc_attr( $class ); ?>" 
                            data-lesson-id="<?php echo esc_attr($loop_lesson_id); ?>">
                                <?php echo $count; ?>
                            </a>
                            <?php
                            $count++;
                        endwhile; 
                        wp_reset_postdata();
                    endif; 
                    ?>
                </div>
            </div>
        </div>  

        <div class="header-right-meta">
            <div class="student-info">
                <span class="student-name"><?php echo esc_html( $current_user->display_name ); ?></span>
                
                <div class="timer-container">
                    <div id="countdown-timer" class="timer">--:--</div>
                    <div class="timer-controls">
                        <button id="btn-timer-pause" class="timer-ctrl-btn" title="Pausar/Retomar">&#10074;&#10074;</button>
                        <button id="btn-timer-reset" class="timer-ctrl-btn" title="Reiniciar">&#10227;</button>
                    </div>
                </div>
            </div>

            <div class="header-actions">
                <div class="finish-btn-wrapper">
                    <?php tutor_lesson_mark_complete_html(); ?>
                </div>
                
                <div class="nav-arrows">
                    <?php if ( $previous_id ) : ?>
                        <a href="<?php echo esc_url( get_permalink( $previous_id ) ); ?>" class="arrow-btn btn-prev">&lt;</a>
                    <?php else : ?>
                        <span class="arrow-btn disabled">&lt;</span>
                    <?php endif; ?>

                    <?php if ( $next_id ) : ?>
                        <a href="<?php echo esc_url( get_permalink( $next_id ) ); ?>" class="arrow-btn btn-next">&gt;</a>
                    <?php else : ?>
                        <span class="arrow-btn disabled">&gt;</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- ÁREA CENTRAL COM MENU LATERAL E TELA DIVIDIDA -->
    <div class="custom-course-main-wrapper">
        
        <!-- MENU LATERAL AGRUPADO POR ÁREAS -->
        <aside class="custom-course-sidebar">
            <h3 class="sidebar-title">Navegação</h3>
            <div class="grouped-menu-accordion">
                <?php
                $topics = tutor_utils()->get_topics( $course_id );
                $grouped_menu = array();

                if ( $topics && $topics->have_posts() ) {
                    while ( $topics->have_posts() ) {
                        $topics->the_post();
                        $loop_topic_id = get_the_ID();
                        $topic_title   = get_the_title();

                        // Separação dinâmica e inteligente por hífen comum ou travessão
                        $split = tutor_custom_split_topic_title( $topic_title );
                        $area_name = $split['area'];
                        $text_name = $split['text'];

                        // Correção: Leitura direta do ID no formato array simples de objetos retornado pela API
                        $contents = tutor_utils()->get_contents_by_topic( $loop_topic_id );
                        $first_lesson_url = '#';
                        
                        if ( ! empty( $contents ) && is_array( $contents ) ) {
                            $first_item = $contents[0];
                            // Obtém o ID de forma segura seja objeto stdClass ou Array associativo
                            $first_id = is_object( $first_item ) ? $first_item->ID : ( isset( $first_item['ID'] ) ? $first_item['ID'] : null );
                            if ( $first_id ) {
                                $first_lesson_url = get_permalink( $first_id );
                            }
                        }

                        // Agrupa por Área no Array
                        $grouped_menu[$area_name][] = array(
                            'topic_id'   => $loop_topic_id,
                            'text_name'  => $text_name,
                            'lesson_url' => $first_lesson_url
                        );
                    }
                    wp_reset_postdata();
                }

                // Renderiza o menu agrupado corretamente
                if ( ! empty( $grouped_menu ) ) :
                    foreach ( $grouped_menu as $area => $items ) :
                        ?>
                        <div class="menu-group">
                            <button class="menu-group-header">
                                <span class="group-title"><?php echo esc_html( $area ); ?></span>
                                <span class="group-arrow">&#9662;</span>
                            </button>
                            <ul class="menu-group-items">
                                <?php foreach ( $items as $item ) : 
                                    $is_active_topic = ( $item['topic_id'] === $topic_id ) ? 'is-active' : '';
                                    ?>
                                    <li class="menu-item-link <?php echo $is_active_topic; ?>">
                                        <a href="<?php echo esc_url( $item['lesson_url'] ); ?>">
                                            <?php echo esc_html( $item['text_name'] ); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php
                    endforeach;
                endif;
                ?>
            </div>
        </aside>

        <!-- CORPO DA LIÇÃO (TELA DIVIDIDA) -->
        <main class="custom-course-body">
            <section class="body-pane pane-left">
                <div class="pane-content">
                    <?php echo $left_content; ?>
                </div>
            </section>

            <div class="pane-divider"></div>

            <section class="body-pane pane-right">
                <div class="pane-content">
                    <?php echo $right_content; ?>
                </div>
            </section>
        </main>
    </div>
</div>

<!-- ==========================================================================
     5. GATILHOS E PAINÉIS LATERAIS (SLIDE-OUT TABS)
     ========================================================================== -->
<div class="slideout-triggers-container">
    <?php if ( ! empty( $tutorial_content ) ) : ?>
        <button class="slideout-tab-btn" data-panel="panel-tutorial">Tutorial</button>
    <?php endif; ?>
    <?php if ( ! empty( $resolution_content ) ) : ?>
        <button class="slideout-tab-btn" data-panel="panel-resolution">Resolução</button>
    <?php endif; ?>
</div>

<!-- Painel de Tutorial -->
<?php if ( ! empty( $tutorial_content ) ) : ?>
<div id="panel-tutorial" class="slideout-panel">
    <div class="panel-header">
        <h4>Tutorial / Dica</h4>
        <button class="panel-close-btn">&times;</button>
    </div>
    <div class="panel-body">
        <?php echo $tutorial_content; ?>
    </div>
</div>
<?php endif; ?>

<!-- Painel de Resolução -->
<?php if ( ! empty( $resolution_content ) ) : ?>
<div id="panel-resolution" class="slideout-panel">
    <div class="panel-header">
        <h4>Resolução Comentada</h4>
        <button class="panel-close-btn">&times;</button>
    </div>
    <div class="panel-body">
        <?php echo $resolution_content; ?>
    </div>
</div>
<?php endif; ?>

<!-- ==========================================================================
     6. SCRIPT JAVASCRIPT
     ========================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var container = document.querySelector('.custom-course-container');
    if (!container) return;

    var topicId = container.getAttribute('data-topic-id');
    var currentLessonId = container.getAttribute('data-current-lesson-id');

    // ==========================================
    // A. CRONÔMETRO PERSISTENTE COM CORREÇÃO DE RESET E PAUSE
    // ==========================================
    var timerKey = 'exam_end_time_topic_' + topicId;
    var pauseKey = 'exam_paused_time_topic_' + topicId;
    var durationMinutes = 50; 
    var display = document.querySelector('#countdown-timer');

    var btnPause = document.querySelector('#btn-timer-pause');
    var btnReset = document.querySelector('#btn-timer-reset');

    var endTime = localStorage.getItem(timerKey);
    var pausedOffset = localStorage.getItem(pauseKey);

    // Criamos a variável do intervalo no escopo global para controle
    var timerInterval = null;

    if (!endTime && !pausedOffset) {
        resetTimer();
    }

    function resetTimer() {
        endTime = Date.now() + (durationMinutes * 60 * 1000);
        localStorage.setItem(timerKey, endTime);
        localStorage.removeItem(pauseKey);
        pausedOffset = null; // Garante que limpou globalmente
        
        if(btnPause) {
            btnPause.innerHTML = "&#10074;&#10074;";
            btnPause.classList.remove('is-paused');
        }
        if (display) {
            display.style.color = "";
        }
    }

    function updateTimer() {
        var remainingTime;

        // Sempre buscar o valor atualizado do localStorage para evitar problemas de escopo
        pausedOffset = localStorage.getItem(pauseKey);

        if (pausedOffset) {
            remainingTime = parseInt(pausedOffset, 10) * 1000;
        } else {
            remainingTime = endTime - Date.now();
        }

        if (remainingTime <= 0) {
            if (display) {
                display.textContent = "00:00";
                display.style.color = "#ff0000";
            }
            clearInterval(timerInterval);
            return;
        }

        var totalSeconds = Math.floor(remainingTime / 1000);
        var minutes = Math.floor(totalSeconds / 60);
        var seconds = totalSeconds % 60;

        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;

        if (display) {
            display.textContent = minutes + ":" + seconds;
        }
    }

    // Função para iniciar/garantir que o loop do timer está rodando
    function startInterval() {
        clearInterval(timerInterval); // Evita múltiplos intervalos rodando juntos
        timerInterval = setInterval(function() {
            // Deixamos o updateTimer rodar sempre, ele mesmo sabe se está pausado ou não
            updateTimer();
        }, 1000);
    }

    if (btnPause) {
        btnPause.addEventListener('click', function() {
            pausedOffset = localStorage.getItem(pauseKey); // Atualiza escopo local

            if (pausedOffset) {
                // Retomar cronômetro
                endTime = Date.now() + (parseInt(pausedOffset, 10) * 1000);
                localStorage.setItem(timerKey, endTime);
                localStorage.removeItem(pauseKey);
                btnPause.innerHTML = "&#10074;&#10074;";
                btnPause.classList.remove('is-paused');
            } else {
                // Pausar cronômetro
                var secondsLeft = Math.floor((endTime - Date.now()) / 1000);
                if (secondsLeft > 0) {
                    localStorage.setItem(pauseKey, secondsLeft);
                    btnPause.innerHTML = "&#9658;";
                    btnPause.classList.add('is-paused');
                }
            }
            updateTimer();
        });
    }

    if (btnReset) {
        btnReset.addEventListener('click', function() {
            if(confirm('Tem certeza que deseja reiniciar o tempo da prova?')) {
                resetTimer();
                updateTimer();
                startInterval(); // Força o reinício do loop em tempo real
            }
        });
    }

    // Inicialização padrão ao carregar a página
    updateTimer();
    startInterval();

    // Limpa chaves ao concluir lição
    var finishBtn = document.querySelector('.tutor-btn-complete-lesson');
    if (finishBtn) {
        finishBtn.addEventListener('click', function() {
            localStorage.removeItem(timerKey);
            localStorage.removeItem(pauseKey);
        });
    }

    // ==========================================
    // B. MENU LATERAL RETRÁTIL (DRAWER) COM PERSISTÊNCIA
    // ==========================================
    var btnToggleSidebar = document.querySelector('#btn-toggle-sidebar');
    var mainWrapper = document.querySelector('.custom-course-main-wrapper');

    var isSidebarClosed = localStorage.getItem('sidebar_closed') === 'true';
    if (isSidebarClosed) {
        mainWrapper.classList.add('sidebar-closed');
    }

    if (btnToggleSidebar) {
        btnToggleSidebar.addEventListener('click', function() {
            mainWrapper.classList.toggle('sidebar-closed');
            var closed = mainWrapper.classList.contains('sidebar-closed');
            localStorage.setItem('sidebar_closed', closed);
        });
    }

    // accordion do menu
    var headers = document.querySelectorAll('.menu-group-header');
    headers.forEach(function(header) {
        header.addEventListener('click', function() {
            var group = this.parentElement;
            group.classList.toggle('is-open');
        });
    });

    var activeItem = document.querySelector('.menu-item-link.is-active');
    if (activeItem) {
        var parentGroup = activeItem.closest('.menu-group');
        if (parentGroup) {
            parentGroup.classList.add('is-open');
        }
    }

    // ==========================================
    // C. ABAS LATERAIS COM CONTROLE DE PAUSA DE MÍDIA
    // ==========================================
    var tabButtons = document.querySelectorAll('.slideout-tab-btn');
    var panels = document.querySelectorAll('.slideout-panel');
    var closeButtons = document.querySelectorAll('.panel-close-btn');

    function stopVideosInPanel(panel) {
        var nativeVideos = panel.querySelectorAll('video');
        nativeVideos.forEach(function(video) {
            video.pause();
        });

        var iframes = panel.querySelectorAll('iframe');
        iframes.forEach(function(iframe) {
            var src = iframe.src;
            iframe.src = ''; 
            iframe.src = src; 
        });
    }

    tabButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var targetId = this.getAttribute('data-panel');
            var targetPanel = document.getElementById(targetId);

            if (targetPanel.classList.contains('is-open')) {
                targetPanel.classList.remove('is-open');
                stopVideosInPanel(targetPanel);
            } else {
                panels.forEach(function(p) {
                    p.classList.remove('is-open');
                    stopVideosInPanel(p);
                });
                targetPanel.classList.add('is-open');
            }
        });
    });

    closeButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var panel = this.closest('.slideout-panel');
            panel.classList.remove('is-open');
            stopVideosInPanel(panel);
        });
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.slideout-panel') && !e.target.closest('.slideout-tab-btn')) {
            panels.forEach(function(p) {
                if (p.classList.contains('is-open')) {
                    p.classList.remove('is-open');
                    stopVideosInPanel(p);
                }
            });
        }
    });

    // ==========================================
    // D. PROGRESSO DE ATIVIDADE & RASTREAMENTO H5P
    // ==========================================
    document.querySelectorAll('.lesson-num-btn').forEach(function(btn) {
        var btnLessonId = btn.getAttribute('data-lesson-id');
        var status = localStorage.getItem('lesson_status_' + btnLessonId);
        
        if (status === 'in-progress' && !btn.classList.contains('active') && !btn.classList.contains('completed')) {
            btn.classList.add('in-progress');
        }
    });

    function markCurrentLessonInProgress() {
        var currentBtn = document.querySelector('.lesson-num-btn[data-lesson-id="' + currentLessonId + '"]');
        localStorage.setItem('lesson_status_' + currentLessonId, 'in-progress');
        if (currentBtn && !currentBtn.classList.contains('completed') && !currentBtn.classList.contains('active')) {
            currentBtn.classList.add('in-progress');
        }
    }

    function bindH5PEvents() {
        if (window.H5P && window.H5P.externalDispatcher) {
            window.H5P.externalDispatcher.on('xAPI', function (event) {
                var statement = (event.data && event.data.statement) ? event.data.statement : null;
                if (statement && statement.verb) {
                    var verbId = statement.verb.id || '';
                    var verbDisplay = (statement.verb.display && statement.verb.display['en-US']) ? statement.verb.display['en-US'] : '';

                    if (verbId.indexOf('/interacted') !== -1 || verbDisplay === 'interacted') {
                        markCurrentLessonInProgress();
                    }
                }
            });
        } else {
            setTimeout(bindH5PEvents, 1000);
        }
    }
    bindH5PEvents();
});
</script>

<?php 
get_tutor_footer();