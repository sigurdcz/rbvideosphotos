(function(){
var b=document.getElementById("scroll-top");
if(!b)return;
window.addEventListener("scroll",function(){b.classList.toggle("vis",window.scrollY>300);},{passive:true});
})();