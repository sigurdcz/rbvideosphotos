(function(){
  var settings=window.cmsCookieConsentSettings||{};
  var enabled=settings.cookieConsentEnabled!==false;
  var analyticsCategoryEnabled=settings.analyticsCategoryEnabled!==false;
  var marketingCategoryEnabled=settings.marketingCategoryEnabled!==false;
  var debugEnabled=false;

  function debugLog(step,payload){
    if(!debugEnabled||!window.console) return;
    try{
      console.groupCollapsed('[cookie-consent] ' + step);
      if(payload!==undefined) console.log(payload);
      console.groupEnd();
    }catch(e){}
  }

  function inspectElement(el,label){
    if(!el) return {label:label||'unknown',missing:true};
    var style=window.getComputedStyle?window.getComputedStyle(el):null;
    var rect=typeof el.getBoundingClientRect==='function'?el.getBoundingClientRect():null;
    return {
      label:label||el.className||el.tagName,
      hidden:!!el.hidden,
      className:el.className,
      dataset:Object.assign({}, el.dataset || {}),
      clientWidth:el.clientWidth,
      clientHeight:el.clientHeight,
      offsetWidth:el.offsetWidth,
      offsetHeight:el.offsetHeight,
      rect:rect?{top:rect.top,left:rect.left,width:rect.width,height:rect.height,bottom:rect.bottom,right:rect.right}:null,
      computed:style?{display:style.display,visibility:style.visibility,opacity:style.opacity,zIndex:style.zIndex,position:style.position,transform:style.transform,pointerEvents:style.pointerEvents}:null
    };
  }

  function createFallbackManager(){
    var key='cookie_consent_preferences';
    var version='1.0';
    function getPreferences(){
      try{
        var raw=window.localStorage?window.localStorage.getItem(key):'';
        if(!raw)return null;
        var parsed=JSON.parse(raw);
        if(!parsed||parsed.version!==version)return null;
        return {necessary:true,analytics:analyticsCategoryEnabled&&parsed.analytics===true,marketing:marketingCategoryEnabled&&parsed.marketing===true,timestamp:parsed.timestamp||new Date().toISOString(),version:version};
      }catch(e){return null;}
    }
    function savePreferences(data){
      var normalized={necessary:true,analytics:analyticsCategoryEnabled&&data&&data.analytics===true,marketing:marketingCategoryEnabled&&data&&data.marketing===true,timestamp:new Date().toISOString(),version:version};
      try{
        if(window.localStorage)window.localStorage.setItem(key,JSON.stringify(normalized));
      }catch(e){}
      document.dispatchEvent(new CustomEvent('cms-cookie-consent:updated',{detail:normalized}));
      return normalized;
    }
    return {getPreferences:getPreferences,savePreferences:savePreferences};
  }

  function initConsent(){
    var manager=window.cmsCookieConsent||createFallbackManager();
    window.cmsCookieConsent=manager;

    var host=document.querySelector('[data-cookie-consent-host]');
    var box=document.querySelector('[data-cookie-consent-modal]');
    var backdrop=document.querySelector('[data-cookie-consent-backdrop]');
    debugLog('init:start',{enabled:enabled,analyticsCategoryEnabled:analyticsCategoryEnabled,marketingCategoryEnabled:marketingCategoryEnabled,hasManager:!!manager,hasHost:!!host,hasBox:!!box,hasBackdrop:!!backdrop,readyState:document.readyState});
    if(!host||!box||!backdrop||!enabled){
      if(host)host.hidden=true;
      if(box)box.hidden=true;
      if(backdrop)backdrop.hidden=true;
      return;
    }

    if(host.parentNode!==document.body){
      document.body.appendChild(host);
    }

    var acceptAll=box.querySelector('[data-cookie-accept-all]');
    var rejectAll=box.querySelector('[data-cookie-reject-all]');
    var settingsBtn=box.querySelector('[data-cookie-settings]');
    var saveBtn=box.querySelector('[data-cookie-save]');

    function pref(){return manager&&manager.getPreferences?manager.getPreferences():null;}
    function save(data){if(manager&&manager.savePreferences)manager.savePreferences(data);}
    function syncSettingsButton(){
      if(!settingsBtn) return;
      var expanded=box.dataset.mode==='settings';
      settingsBtn.textContent=expanded?'Skrýt nastavení':'Nastaveni';
      settingsBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
    function sync(){
      var p=pref()||{analytics:false,marketing:false};
      box.querySelectorAll('[data-cookie-category]').forEach(function(input){
        input.checked=p[input.dataset.cookieCategory]===true;
      });
    }
    function syncCategoryVisibility(){
      var analyticsRow=box.querySelector('[data-cookie-option="analytics"]');
      var marketingRow=box.querySelector('[data-cookie-option="marketing"]');
      if(analyticsRow) analyticsRow.hidden=!analyticsCategoryEnabled;
      if(marketingRow) marketingRow.hidden=!marketingCategoryEnabled;
    }
    function show(mode){
      sync();
      syncCategoryVisibility();
      var selectedMode=mode==='settings'?'settings':'summary';
      host.hidden=false;
      box.dataset.mode=selectedMode;
      box.hidden=false;
      backdrop.hidden=selectedMode!=='settings';
      host.style.display='block';
      box.style.display='block';
      backdrop.style.display=selectedMode==='settings'?'block':'none';
      box.style.visibility='visible';
      backdrop.style.visibility=selectedMode==='settings'?'visible':'hidden';
      box.style.opacity='1';
      backdrop.style.opacity=selectedMode==='settings'?'1':'0';
      box.setAttribute('role',selectedMode==='settings'?'dialog':'region');
      // aria-modal is only valid on a dialog. Leaving aria-modal="false" on the region
      // banner failed aria-allowed-attr, which cost both Accessibility and Agentic Browsing.
      if(selectedMode==='settings'){box.setAttribute('aria-modal','true');}
      else{box.removeAttribute('aria-modal');}
      syncSettingsButton();
    }
    function hide(){
      host.hidden=true;
      box.hidden=true;
      backdrop.hidden=true;
      host.style.display='none';
      box.style.display='none';
      backdrop.style.display='none';
      box.dataset.mode='summary';
      syncSettingsButton();
    }
    function openSettings(){ show('settings'); }
    function closeSettings(){
      if(pref()) hide();
      else show('summary');
    }
    function toggleSettings(){
      if(box.dataset.mode==='settings'){
        closeSettings();
        return;
      }
      openSettings();
    }
    function bindOpeners(scope){
      (scope||document).querySelectorAll('[data-cookie-open-settings]').forEach(function(el){
        if(el.dataset.cookieBound==='1') return;
        el.dataset.cookieBound='1';
        el.addEventListener('click',function(e){
          e.preventDefault();
          openSettings();
        });
      });
    }
    function addFooterLink(){
      if(document.querySelector('.cookie-consent-link[data-cookie-open-settings]'))return;
      var footer=document.querySelector('.site-footer-bottom')||document.querySelector('.site-footer-rich')||document.querySelector('footer');
      if(!footer)return;
      var button=document.createElement('button');
      button.type='button';
      button.className='cookie-consent-link';
      button.dataset.cookieOpenSettings='1';
      button.textContent='Nastavení cookies';
      if (footer.classList && footer.classList.contains('site-footer-bottom')) {
        footer.appendChild(document.createTextNode(' · '));
      }
      footer.appendChild(button);
      bindOpeners(footer);
    }

    addFooterLink();
    bindOpeners(document);

    if(backdrop.dataset.cookieBound!=='1'){
      backdrop.dataset.cookieBound='1';
      backdrop.addEventListener('click',closeSettings);
    }
    if(acceptAll&&acceptAll.dataset.cookieBound!=='1'){
      acceptAll.dataset.cookieBound='1';
      acceptAll.addEventListener('click',function(){
        save({necessary:true,analytics:true,marketing:true});
        hide();
      });
    }
    if(rejectAll&&rejectAll.dataset.cookieBound!=='1'){
      rejectAll.dataset.cookieBound='1';
      rejectAll.addEventListener('click',function(){
        save({necessary:true,analytics:false,marketing:false});
        hide();
      });
    }
    if(settingsBtn&&settingsBtn.dataset.cookieBound!=='1'){
      settingsBtn.dataset.cookieBound='1';
      settingsBtn.addEventListener('click',toggleSettings);
    }
    if(saveBtn&&saveBtn.dataset.cookieBound!=='1'){
      saveBtn.dataset.cookieBound='1';
      saveBtn.addEventListener('click',function(){
        var data={necessary:true,analytics:false,marketing:false};
        box.querySelectorAll('[data-cookie-category]').forEach(function(input){
          data[input.dataset.cookieCategory]=input.checked===true;
        });
        save(data);
        hide();
      });
    }

    if(document.documentElement.dataset.cookieEscBound!=='1'){
      document.documentElement.dataset.cookieEscBound='1';
      document.addEventListener('keydown',function(e){
        if(e.key==='Escape'&&!box.hidden){
          if(box.dataset.mode==='settings') closeSettings();
        }
      });
    }

    if(!pref()){
      show('summary');
    } else {
      hide();
    }
  }

  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded', initConsent, {once:true});
  } else {
    initConsent();
  }
})();
