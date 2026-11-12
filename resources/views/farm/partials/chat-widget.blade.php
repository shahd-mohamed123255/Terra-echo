<div id="chatPanel" class="chat-panel" hidden>
    <div class="chat-header">
        <img src="{{ asset('images/abu-qerdan.jpg') }}" alt="Abu Qerdan" class="chat-avatar">
        <div class="chat-title">
            <strong>Abu Qerdan</strong>
            <small>Your Smart Agricultural Assistant</small>
        </div>
        <button type="button" id="chatMinimize" aria-label="Minimize">&minus;</button>
        <button type="button" id="chatClose" aria-label="Close">&times;</button>
    </div>

    <div class="chat-body">
        <div id="chatMessages" class="chat-messages"></div>
        <div id="chatTyping" class="chat-typing" hidden><span></span><span></span><span></span></div>
    </div>

    <form id="chatForm" class="chat-form" autocomplete="off">
        <input type="text" id="chatInput" maxlength="2000" dir="auto" placeholder="Ask about your farm...">
        <button type="submit" id="chatSend" aria-label="Send">&#10148;</button>
    </form>
    <p class="chat-note">AI can be mistaken. Check advice with a local expert.</p>
</div>