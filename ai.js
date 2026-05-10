const conversationHistory = [];

async function sendMessage() {

  const input = document.getElementById("user-input");
  const message = input.value.trim();

  if (!message) return;

  const chatBox = document.getElementById("chat-box");

  // User message
  const userDiv = document.createElement("div");
  userDiv.className = "user-message";
  userDiv.innerText = message;
  chatBox.appendChild(userDiv);

  input.value = "";

  // Save history
  conversationHistory.push({
    role: "user",
    content: message
  });

  chatBox.scrollTop = chatBox.scrollHeight;

  // Typing indicator
  const typingDiv = document.createElement("div");
  typingDiv.className = "bot-message";
  typingDiv.innerText = "Typing...";
  chatBox.appendChild(typingDiv);

  try {

    const response = await fetch("ai.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        messages: conversationHistory
      })
    });

    const data = await response.json();

    // Remove typing
    chatBox.removeChild(typingDiv);

    const reply = data.reply || "No response";

    // Save AI reply
    conversationHistory.push({
      role: "assistant",
      content: reply
    });

    const botDiv = document.createElement("div");
    botDiv.className = "bot-message";
    botDiv.innerText = reply;

    chatBox.appendChild(botDiv);

    chatBox.scrollTop = chatBox.scrollHeight;

  } catch (error) {

    chatBox.removeChild(typingDiv);

    const errDiv = document.createElement("div");
    errDiv.className = "bot-message";
    errDiv.innerText = "⚠️ Error connecting to AI server.";

    chatBox.appendChild(errDiv);
  }
}

// Enter key support
document.getElementById("user-input")
.addEventListener("keydown", function(e){
  if(e.key === "Enter"){
    sendMessage();
  }
});