(function(){
var nav = document.getElementById('sitenav');
var heroBg = document.querySelector('.hero-bg');
var btn = document.getElementById('scroll-top');
if (nav && document.getElementById('cms-adminbar')){nav.style.top = '40px';}
window.addEventListener('scroll',function(){
var y = window.pageYOffset;
if (btn) btn.classList.toggle('vis',y > 300);
if (heroBg) heroBg.style.transform = 'translateY(' + (y * 0.32) + 'px)';
if (nav) nav.style.background = y > 80 ? 'rgba(10,10,10,.98)' : 'rgba(17,17,17,.96)';
},{passive: true});
})();