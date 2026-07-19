(function(){
  var body=document.body;if(!body||!body.classList.contains('theme-premium-photo'))return;
  var toggle=document.querySelector('.theme-premium-photo .nav-toggle');var nav=document.querySelector('.theme-premium-photo .pp-nav');
  if(toggle&&nav){toggle.addEventListener('click',function(){var open=nav.classList.toggle('is-open');toggle.setAttribute('aria-expanded',open?'true':'false')});nav.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(){nav.classList.remove('is-open');toggle.setAttribute('aria-expanded','false')})})}
  document.querySelectorAll('.theme-premium-photo .hp-hero,.theme-premium-photo .hero').forEach(function(hero){hero.classList.add('pp-hero')});
  document.querySelectorAll('.theme-premium-photo .hp-svc-card,.theme-premium-photo .bl-card,.theme-premium-photo .pt-tile').forEach(function(card){card.addEventListener('mouseenter',function(){card.classList.add('is-hover')});card.addEventListener('mouseleave',function(){card.classList.remove('is-hover')})});
})();
