<!DOCTYPE html>
<html>
<head>
<title>AI Assistant - PrepHub</title>

<style>
body{
background: radial-gradient(circle at top,#0a1b3d,#020617);
color:white;
font-family:'Poppins',sans-serif;
margin:0;
}

/* HEADER */
header{
display:flex;
justify-content:space-between;
padding:20px 60px;
}
nav a{
color:#bfc6d4;
margin-left:20px;
text-decoration:none;
}

/* CHAT BOX */
.chat-container{
width:800px;
margin:80px auto;
background:rgba(255,255,255,0.05);
padding:25px;
border-radius:18px;
backdrop-filter:blur(10px);
box-shadow:0 0 30px rgba(0,0,0,0.3);
}

.chat-box{
height:400px;
overflow-y:auto;
scroll-behavior:smooth;
}

/* SCROLLBAR */
.chat-box::-webkit-scrollbar{
width:8px;
}
.chat-box::-webkit-scrollbar-thumb{
background:linear-gradient(180deg,#6366f1,#8b5cf6);
border-radius:10px;
}

/* MESSAGES */
.user-message{
background:linear-gradient(135deg,#6366f1,#8b5cf6);
padding:10px;
border-radius:12px;
margin:6px;
text-align:right;
max-width:60%;
margin-left:auto;
}

.bot-message{
background:#1f2937;
padding:10px;
border-radius:12px;
margin:6px;
max-width:70%;
}

/* INPUT */
.chat-input{margin-top:10px;}

.input-wrapper{
display:flex;
align-items:center;
background:rgba(255,255,255,0.08);
border-radius:30px;
padding:10px;
gap:10px;
position:relative;
}

.input-wrapper input{
flex:1;
background:none;
border:none;
outline:none;
color:white;
}

.upload-btn{cursor:pointer;}

.send-btn{
background:#6366f1;
border:none;
padding:10px;
border-radius:50%;
color:white;
cursor:pointer;
}

/* TOOLS */
.tools{
position:relative;
}

.plus-btn{
background:none;
border:none;
color:#cbd5f5;
font-size:18px;
cursor:pointer;
}

/* TOOL MENU */
.tool-menu{
position:absolute;
bottom:55px;
left:0;
background:#111827;
border-radius:12px;
padding:10px;
display:none;
flex-direction:column;
gap:6px;
box-shadow:0 0 15px rgba(0,0,0,0.6);
z-index:100;
animation:fadeIn 0.2s ease;
}

.tool-menu button{
background:none;
border:none;
color:white;
padding:8px;
text-align:left;
cursor:pointer;
border-radius:6px;
}

.tool-menu button:hover{
background:#374151;
}

/* animation */
@keyframes fadeIn{
from{opacity:0; transform:translateY(10px);}
to{opacity:1; transform:translateY(0);}
}

/* IMAGE */
.chat-box img{
max-width:200px;
border-radius:10px;
margin:5px;
}
</style>

<script src="https://js.puter.com/v2/"></script>

</head>

<body>

<header>
<h2>PrepHub</h2>
<nav>
<a href="index.html">Home</a>
<a href="subject.html">Subjects</a>
<a href="login.html">Login</a>
</nav>
</header>

<div class="chat-container">

<div id="chat-box" class="chat-box">
<div class="bot-message">Hello! I'm your PrepHub AI for your Doubt Solving 🤖</div>
</div>

<div class="chat-input">
<div class="input-wrapper">

<div class="tools">
<button class="plus-btn" onclick="toggleMenu()">➕</button>

<div id="tool-menu" class="tool-menu">
<button onclick="selectTool('chat')">💬 Chat</button>
<button onclick="selectTool('image')">🖼️ Image</button> 
<button onclick="selectTool('code')">💻 Code</button>
</div>
</div>

<input id="user-input" placeholder="Message Your Doubt...">

<label class="upload-btn">

<input type="file" id="image-input" hidden>
</label>

<button class="send-btn" onclick="sendMessage()">➤</button>

</div>
</div>

</div>

<script>

let mode="chat";
let lastResponse="";

// 🔊 SPEAK
async function speakLast(){
if(!lastResponse) return;

const audio = await puter.ai.txt2speech(lastResponse,{provider:"openai"});
audio.setAttribute("controls","");
document.body.appendChild(audio);
}

// 🚀 MAIN FUNCTION
async function sendMessage(){

const input=document.getElementById("user-input");
const fileInput=document.getElementById("image-input");

const message=input.value.trim();
const file=fileInput.files[0];

const chatBox=document.getElementById("chat-box");

if(!message && !file) return;

// USER MESSAGE
if(message){
const u=document.createElement("div");
u.className="user-message";
u.innerText=message;
chatBox.appendChild(u);
}

// FILE PREVIEW
if(file){
const f=document.createElement("div");
f.className="user-message";

if(file.type.startsWith("image/")){
const imgPreview=document.createElement("img");
imgPreview.src=URL.createObjectURL(file);
f.appendChild(imgPreview);
}else{
f.innerText="📎 " + file.name;
}

chatBox.appendChild(f);
}

input.value="";
fileInput.value="";

// BOT MESSAGE
const bot=document.createElement("div");
bot.className="bot-message";
bot.innerText="Typing...";
chatBox.appendChild(bot);

try{

// 🖼️ IMAGE GENERATION MODE
if(mode==="image" && message){
const img=await puter.ai.txt2img(message,{model:"gpt-image-1.5"});
chatBox.removeChild(bot);
chatBox.appendChild(img);
return;
}

let prompt = message || "Analyze this file";

// 📂 FILE HANDLING (IMPORTANT PART)
if(file){

// 🖼️ IMAGE → send directly
if(file.type.startsWith("image/")){
const res = await puter.ai.chat(
message || "Explain this image",
file,
{model:"gpt-5.4-nano"}
);
bot.innerText = res;
lastResponse = res;
return;
}

// 📄 TEXT / CODE FILES
const textTypes = [
"text/plain","application/json","application/javascript",
"text/html","text/css","text/csv"
];

if(textTypes.includes(file.type) || file.name.match(/\.(txt|js|html|css|json|py|java|cpp|c|php)$/)){

const content = await file.text();

prompt = `Analyze this file:

Filename: ${file.name}

Content:
${content}`;
}

// 📦 OTHER FILES (PDF, DOCX, etc.)
else{
prompt = `User uploaded a file: ${file.name}
File type: ${file.type}

Explain what this file might contain and how to use it.`;
}
}

// 💻 CODE MODE
if(mode==="code"){
prompt = "Write clean code with explanation:\n" + prompt;
}

// 🔥 STREAM RESPONSE
const stream = await puter.ai.chat(prompt,{
model:"gpt-5.4-nano",
stream:true,
tools:[{type:"web_search"}]
});

bot.innerText="";
let full="";

for await (const part of stream){
const t=part?.text||"";
full+=t;
bot.innerText=full;
chatBox.scrollTop=chatBox.scrollHeight;
}

lastResponse=full;

}catch(e){
bot.innerText="⚠️ Error handling file";
console.error(e);
}
}

// ENTER KEY
document.getElementById("user-input").addEventListener("keydown",e=>{
if(e.key==="Enter") sendMessage();
});

// TOGGLE MENU
function toggleMenu(){
const menu=document.getElementById("tool-menu");
menu.style.display = menu.style.display === "flex" ? "none" : "flex";
}

// SELECT TOOL
function selectTool(selectedMode){
mode = selectedMode;

const input=document.getElementById("user-input");

if(selectedMode==="chat"){
input.placeholder="Ask anything...";
}
else if(selectedMode==="image"){
input.placeholder="Describe image to generate...";
}
else if(selectedMode==="code"){
input.placeholder="Ask for code...";
}

document.getElementById("tool-menu").style.display="none";
}

// CLICK OUTSIDE CLOSE
document.addEventListener("click",(e)=>{
const menu=document.getElementById("tool-menu");
const plus=document.querySelector(".plus-btn");

if(!menu.contains(e.target) && !plus.contains(e.target)){
menu.style.display="none";
}
});

</script>

</body>
</html>