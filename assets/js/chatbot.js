(function () {
  const STORAGE_KEY = "tauro_chatbot_messages";
  const PANEL_STATE_KEY = "tauro_chatbot_open";
  const DEFAULT_SUGGESTIONS = [
    "Arma un outfit",
    "Ver chaquetas negras",
    "Guia de tallas",
    "Consultar pedido con token"
  ];
  const RESET_COMMANDS = [
    "reiniciar chat",
    "nuevo chat",
    "borrar chat",
    "reset chat"
  ];
  const MAX_STORED_MESSAGES = 36;

  function normalizeText(text) {
    return String(text || "")
      .trim()
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9\s]/g, " ")
      .replace(/\s+/g, " ")
      .trim();
  }

  function normalizeSuggestions(suggestions) {
    if (!Array.isArray(suggestions)) {
      return DEFAULT_SUGGESTIONS.slice();
    }

    const clean = suggestions
      .map((item) => String(item || "").trim())
      .filter((item, index, array) => item && array.indexOf(item) === index)
      .slice(0, 4);

    return clean.length ? clean : DEFAULT_SUGGESTIONS.slice();
  }

  function getInitialMessages(config) {
    return [
      {
        role: "bot",
        text: "Hola. Soy el asistente de Tauro Store. Puedo ayudarte sin necesidad de iniciar sesion."
      },
      {
        role: "bot",
        text: "Puedes preguntarme por productos del catalogo, tallas, envios, compras, estilo masculino o dudas generales."
      },
      {
        role: "bot",
        text: "Si tienes un token publico de factura, tambien puedo revisar el estado general de tu pedido. Acepto el token completo, el enlace publico o un token abreviado tipo abc123...xyz789. Si necesitas apoyo humano, puedes escribir a WhatsApp al " + config.whatsapp + "."
      }
    ];
  }

  function appendFormattedText(container, text) {
    const safeText = String(text || "");
    const lines = safeText.split(/\n/);

    lines.forEach((line, lineIndex) => {
      const parts = line.split(/(https?:\/\/[^\s]+)/g);

      parts.forEach((part) => {
        if (!part) {
          return;
        }

        if (/^https?:\/\/[^\s]+$/i.test(part)) {
          const link = document.createElement("a");
          link.href = part;
          link.target = "_blank";
          link.rel = "noopener noreferrer";
          link.textContent = part;
          container.appendChild(link);
          return;
        }

        container.appendChild(document.createTextNode(part));
      });

      if (lineIndex < lines.length - 1) {
        container.appendChild(document.createElement("br"));
      }
    });
  }

  function createMessageElement(message) {
    const wrapper = document.createElement("div");
    const roleClass = message.role === "user" ? "chatbot-message-user" : "chatbot-message-bot";
    const pendingClass = message.pending ? " chatbot-message-pending" : "";

    wrapper.className = "chatbot-message " + roleClass + pendingClass;

    const bubble = document.createElement("div");
    bubble.className = "chatbot-bubble";

    if (message.pending) {
      bubble.classList.add("is-thinking");

      for (let i = 0; i < 3; i += 1) {
        const dot = document.createElement("span");
        dot.className = "chatbot-dot";
        bubble.appendChild(dot);
      }
    } else {
      appendFormattedText(bubble, message.text);
    }

    wrapper.appendChild(bubble);
    return wrapper;
  }

  function persistMessages(messages) {
    try {
      const safeMessages = messages
        .filter((item) => item && typeof item.text === "string" && !item.pending)
        .slice(-MAX_STORED_MESSAGES);

      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(safeMessages));
    } catch (error) {
      // Si el storage no esta disponible (modo privado, cuota, etc.), no bloqueamos el chat.
    }
  }

  function loadMessages(config) {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      const parsed = JSON.parse(raw || "[]");

      if (Array.isArray(parsed) && parsed.length) {
        return {
          hasStoredConversation: true,
          messages: parsed.filter((item) => item && typeof item.text === "string")
        };
      }
    } catch (error) {
      // Si no hay nada almacenado o el JSON esta corrupto, iniciamos conversacion limpia.
    }

    return {
      hasStoredConversation: false,
      messages: getInitialMessages(config)
    };
  }

  function buildClientFallbackResponse(message, config) {
    const normalized = normalizeText(message);

    if (normalized.includes("pedido") || normalized.includes("seguimiento")) {
      return {
        text: "Desde el navegador no pude completar la consulta avanzada del pedido. Si tienes el token publico de la factura, intenta pegarlo de nuevo. Para soporte humano puedes escribir a WhatsApp al " + config.whatsapp + ".",
        suggestions: ["Consultar pedido con token", "Facturas", "Cambios o cancelaciones", "WhatsApp"]
      };
    }

    if (normalized.includes("talla") || normalized.includes("fit")) {
      return {
        text: "Puedo orientarte con tallas y ajuste. Si me dices tu altura, contextura o el tipo de fit que buscas, te ayudo a aterrizar mejor la recomendacion.",
        suggestions: ["Guia de tallas", "Fit regular o slim", "Arma un outfit", "WhatsApp"]
      };
    }

    if (normalized.includes("outfit") || normalized.includes("combinar") || normalized.includes("look")) {
      return {
        text: "Puedo ayudarte con combinaciones y estilo. Cuentame para que ocasion te quieres vestir o que prenda quieres usar como base, y te propongo algo mas afinado.",
        suggestions: ["Outfit casual elegante", "Look para oficina", "Que zapatos combinar", "Colores que combinan"]
      };
    }

    return {
      text: "Ahora mismo no pude conectarme con la respuesta ampliada del asistente, pero sigo disponible para ayudarte con compras, estilo, tallas, envios y cuidado de prendas.",
      suggestions: DEFAULT_SUGGESTIONS.slice()
    };
  }

  document.addEventListener("DOMContentLoaded", () => {
    const toggleButton = document.getElementById("btnChatbot");
    const closeButton = document.getElementById("btnCerrarChatbot");
    const panel = document.getElementById("chatbotPanel");
    const messagesContainer = document.getElementById("chatbotMessages");
    const suggestionsContainer = document.getElementById("chatbotSuggestions");
    const form = document.getElementById("chatbotForm");
    const input = document.getElementById("chatbotInput");
    const submitButton = form ? form.querySelector(".chatbot-send") : null;

    if (!toggleButton || !closeButton || !panel || !messagesContainer || !suggestionsContainer || !form || !input || !submitButton) {
      return;
    }

    const config = {
      apiUrl: panel.dataset.apiUrl || "chatbot_api.php",
      csrfToken: panel.dataset.csrfToken || "",
      whatsapp: panel.dataset.whatsapp || "+57 317 537 8274"
    };

    const loadedState = loadMessages(config);
    let messages = loadedState.messages;
    let pendingMessage = null;
    let shouldResetConversation = !loadedState.hasStoredConversation;
    let currentSuggestions = DEFAULT_SUGGESTIONS.slice();

    function renderMessages() {
      messagesContainer.innerHTML = "";

      messages.forEach((message) => {
        messagesContainer.appendChild(createMessageElement(message));
      });

      if (pendingMessage) {
        messagesContainer.appendChild(createMessageElement(pendingMessage));
      }

      messagesContainer.scrollTop = messagesContainer.scrollHeight;
      persistMessages(messages);
    }

    function renderSuggestions(suggestions) {
      currentSuggestions = normalizeSuggestions(suggestions);
      suggestionsContainer.innerHTML = "";

      currentSuggestions.forEach((suggestion) => {
        const chip = document.createElement("button");
        chip.type = "button";
        chip.className = "chatbot-chip";
        chip.textContent = suggestion;
        chip.disabled = form.classList.contains("is-busy");
        chip.addEventListener("click", () => {
          processUserMessage(suggestion);
        });
        suggestionsContainer.appendChild(chip);
      });
    }

    function openPanel() {
      panel.classList.add("is-open");
      toggleButton.classList.add("is-hidden");

      try {
        sessionStorage.setItem(PANEL_STATE_KEY, "open");
      } catch (error) {
        // Guardar el estado del panel es opcional; si falla, seguimos normal.
      }

      window.setTimeout(() => input.focus(), 120);
    }

    function closePanel() {
      panel.classList.remove("is-open");
      toggleButton.classList.remove("is-hidden");

      try {
        sessionStorage.setItem(PANEL_STATE_KEY, "closed");
      } catch (error) {
        // Guardar el estado del panel es opcional; si falla, seguimos normal.
      }
    }

    function setBusy(isBusy) {
      form.classList.toggle("is-busy", isBusy);
      input.disabled = isBusy;
      submitButton.disabled = isBusy;

      suggestionsContainer.querySelectorAll(".chatbot-chip").forEach((chip) => {
        chip.disabled = isBusy;
      });
    }

    function addMessage(role, text) {
      messages.push({
        role,
        text: String(text || "").trim()
      });

      if (messages.length > MAX_STORED_MESSAGES) {
        messages = messages.slice(-MAX_STORED_MESSAGES);
      }

      renderMessages();
    }

    function addBotMessage(answer) {
      addMessage("bot", answer.text);
      renderSuggestions(answer.suggestions);
    }

    function showThinkingMessage() {
      pendingMessage = {
        role: "bot",
        pending: true
      };
      renderMessages();
    }

    function hideThinkingMessage() {
      pendingMessage = null;
      renderMessages();
    }

    async function requestAssistantReply(message) {
      if (!window.fetch) {
        return buildClientFallbackResponse(message, config);
      }

      const requestBody = {
        message,
        csrf_token: config.csrfToken,
        reset: shouldResetConversation
      };

      const response = await fetch(config.apiUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest"
        },
        body: JSON.stringify(requestBody)
      });

      let data = null;

      try {
        data = await response.json();
      } catch (error) {
        data = null;
      }

      shouldResetConversation = !!(data && data.reset);

      if (!response.ok || !data || !data.ok) {
        if (data && typeof data.message === "string" && data.message.trim()) {
          return {
            text: data.message.trim(),
            suggestions: normalizeSuggestions(data.suggestions)
          };
        }

        return buildClientFallbackResponse(message, config);
      }

      shouldResetConversation = false;

      return {
        text: String(data.message || "").trim() || buildClientFallbackResponse(message, config).text,
        suggestions: normalizeSuggestions(data.suggestions),
        mode: data.mode || "fallback"
      };
    }

    function resetConversation(announce) {
      messages = getInitialMessages(config);
      pendingMessage = null;
      shouldResetConversation = true;
      persistMessages(messages);
      renderMessages();
      renderSuggestions(DEFAULT_SUGGESTIONS);

      if (announce) {
        addMessage("bot", "Reinicie la conversacion. Cuentame que te gustaria resolver.");
      }
    }

    async function processUserMessage(message) {
      const cleanMessage = String(message || "").trim();

      if (!cleanMessage || form.classList.contains("is-busy")) {
        return;
      }

      if (RESET_COMMANDS.includes(normalizeText(cleanMessage))) {
        resetConversation(true);
        input.value = "";
        return;
      }

      addMessage("user", cleanMessage);
      input.value = "";
      showThinkingMessage();
      setBusy(true);

      try {
        const answer = await requestAssistantReply(cleanMessage);
        hideThinkingMessage();
        addBotMessage(answer);
      } catch (error) {
        hideThinkingMessage();
        addBotMessage(buildClientFallbackResponse(cleanMessage, config));
      } finally {
        setBusy(false);
        input.focus();
      }
    }

    toggleButton.addEventListener("click", openPanel);
    closeButton.addEventListener("click", closePanel);

    form.addEventListener("submit", (event) => {
      event.preventDefault();
      processUserMessage(input.value);
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && panel.classList.contains("is-open")) {
        closePanel();
      }
    });

    document.addEventListener("click", (event) => {
      if (!panel.classList.contains("is-open")) {
        return;
      }

      if (panel.contains(event.target) || toggleButton.contains(event.target)) {
        return;
      }

      closePanel();
    });

    renderMessages();
    renderSuggestions(DEFAULT_SUGGESTIONS);

    try {
      if (sessionStorage.getItem(PANEL_STATE_KEY) === "open") {
        openPanel();
      }
    } catch (error) {
      // Si sessionStorage falla, no forzamos estado del panel.
    }
  });
})();
