<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="nhraa-chat-widget-container" class="nhraa-chat-widget-container">
    <!-- Chat Bubble Toggle -->
    <button id="nhraa-chat-toggle" class="nhraa-chat-toggle" aria-label="Toggle AI Assistant">
        <span class="nhraa-ai-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 16C14.2091 16 16 14.2091 16 12C16 9.79086 14.2091 8 12 8C9.79086 8 8 9.79086 8 12C8 14.2091 9.79086 16 12 16Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M2 12H4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M20 12H22" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M12 2V4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M12 20V22" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M4.93 4.93L6.34 6.34" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M17.66 17.66L19.07 19.07" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M19.07 4.93L17.66 6.34" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M6.34 17.66L4.93 19.07" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
    </button>

    <!-- Chat Window -->
    <div id="nhraa-chat-window" class="nhraa-chat-window nhraa-hidden">
        <div class="nhraa-chat-header">
            <div class="nhraa-header-title">
                <span class="nhraa-header-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 11.5C21 16.7467 16.7467 21 11.5 21C6.25329 21 2 16.7467 2 11.5C2 6.25329 6.25329 2 11.5 2C16.7467 2 21 6.25329 21 11.5Z" stroke="white" stroke-width="2"/>
                        <path d="M11.5 2V21" stroke="white" stroke-width="1.5"/>
                        <path d="M2 11.5H21" stroke="white" stroke-width="1.5"/>
                        <path d="M7 3C7 3 9.5 6 9.5 11.5C9.5 17 7 21 7 21" stroke="white" stroke-width="1.5"/>
                        <path d="M16 3C16 3 13.5 6 13.5 11.5C13.5 17 16 21 16 21" stroke="white" stroke-width="1.5"/>
                    </svg>
                </span>
                <div class="nhraa-header-text">
                    <h3><?php esc_html_e( 'AI Site Assistant', 'nhrrob-ai-assistant' ); ?></h3>
                    <span class="nhraa-header-badge"><?php esc_html_e( 'AI POWERED', 'nhrrob-ai-assistant' ); ?></span>
                </div>
            </div>
            <button id="nhraa-chat-close" class="nhraa-chat-close" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        
        <div class="nhraa-chat-history" id="nhraa-chat-history">
            <div class="nhraa-message nhraa-assistant">
                <div class="nhraa-message-content">
                    <p><?php esc_html_e( 'Hello! I am your AI assistant specialized in WordPress development and management. How can I help you improve your website today?', 'nhrrob-ai-assistant' ); ?></p>
                </div>
            </div>
            <!-- Dynamic messages will be appended here -->
            <div id="nhraa-loading" class="nhraa-loading" style="display: none;">
                <div class="nhraa-dot-flashing"></div>
            </div>
        </div>

        <div class="nhraa-chat-input-area">
            <div class="nhraa-input-wrapper">
                <textarea id="nhraa-chat-input" placeholder="<?php esc_attr_e( 'E.g., Update site colors to match brand', 'nhrrob-ai-assistant' ); ?>" rows="1"></textarea>
            </div>
            <div class="nhraa-chat-controls">
                <span class="nhraa-char-count" id="nhraa-char-count">0 / 500</span>
                <button type="button" id="nhraa-chat-send">
                    <?php esc_html_e( 'Send Request', 'nhrrob-ai-assistant' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>
