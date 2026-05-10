<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="nhraa-chat-widget-container" class="nhraa-chat-widget-container">
    <!-- Chat Bubble Toggle -->
    <button id="nhraa-chat-toggle" class="nhraa-chat-toggle" aria-label="Toggle AI Assistant">
        <span class="nhraa-ai-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M11.5 2.5L13.8 8.2L19.5 10.5L13.8 12.8L11.5 18.5L9.2 12.8L3.5 10.5L9.2 8.2L11.5 2.5Z" fill="white"/>
                <path d="M18.5 16.5L19.5 19L22 20L19.5 21L18.5 23.5L17.5 21L15 20L17.5 19L18.5 16.5Z" fill="white"/>
            </svg>
        </span>
    </button>

    <!-- Chat Window -->
    <div id="nhraa-chat-window" class="nhraa-chat-window nhraa-hidden">
        <div class="nhraa-chat-header">
            <div class="nhraa-header-title">
                <span class="nhraa-header-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M11.5 2.5L13.8 8.2L19.5 10.5L13.8 12.8L11.5 18.5L9.2 12.8L3.5 10.5L9.2 8.2L11.5 2.5Z" fill="white"/>
                        <path d="M18.5 16.5L19.5 19L22 20L19.5 21L18.5 23.5L17.5 21L15 20L17.5 19L18.5 16.5Z" fill="white"/>
                    </svg>
                </span>
                <h3><?php esc_html_e( 'AI Developer Assistant', 'nhrrob-ai-assistant' ); ?></h3>
            </div>
            <button id="nhraa-chat-close" class="nhraa-chat-close" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        
        <div class="nhraa-chat-history" id="nhraa-chat-history">
            <div class="nhraa-message nhraa-assistant">
                <div class="nhraa-message-content">
                    <p><?php esc_html_e( 'Hello! I am your AI developer. How can I help you improve your site today?', 'nhrrob-ai-assistant' ); ?></p>
                </div>
            </div>
            <!-- Dynamic messages will be appended here -->
            <div id="nhraa-loading" class="nhraa-loading" style="display: none;">
                <span class="nhraa-spin"></span> <?php esc_html_e( 'Thinking...', 'nhrrob-ai-assistant' ); ?>
            </div>
        </div>

        <div class="nhraa-chat-input-area">
            <textarea id="nhraa-chat-input" placeholder="<?php esc_attr_e( 'E.g., Make my header sticky', 'nhrrob-ai-assistant' ); ?>" rows="1"></textarea>
            <div class="nhraa-chat-controls">
                <span class="nhraa-char-count" id="nhraa-char-count">0 / 500</span>
                <button type="button" id="nhraa-chat-send" class="button button-primary">
                    <?php esc_html_e( 'Send', 'nhrrob-ai-assistant' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>
