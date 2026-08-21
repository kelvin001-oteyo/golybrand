const API='../backend/api.php';
let CURRENT_USER=null;
let TEAM={level1:[],level2:[],level3:[]};
let WITHDRAWALS=[];
let BALANCE={available:0,earned:0,used:0,level1_count:0,level2_count:0,level3_count:0};

async function api(action,payload={}){
 const r=await fetch(`${API}?action=${encodeURIComponent(action)}`,{
  method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(payload)
 });
 const d=await r.json().catch(()=>({success:false,message:'Invalid server response.'}));
 if(!r.ok||d.success===false) throw new Error(d.message||'Request failed.');
 return d;
}
function esc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function money(n){return Number(n||0).toLocaleString('en-KE',{minimumFractionDigits:2,maximumFractionDigits:2});}
function msg(id,text,type='error'){const e=document.getElementById(id);if(e)e.innerHTML=`<div class="message ${type}">${esc(text)}</div>`;}
function clearMsg(id){const e=document.getElementById(id);if(e)e.innerHTML='';}
function showAuthForm(type){
 const l=document.getElementById('loginForm'),r=document.getElementById('registerForm');
 const lt=document.getElementById('loginTab'),rt=document.getElementById('registerTab');
 if(type==='register'){l.classList.add('hidden');r.classList.remove('hidden');lt.classList.remove('active');rt.classList.add('active');}
 else{r.classList.add('hidden');l.classList.remove('hidden');rt.classList.remove('active');lt.classList.add('active');}
 clearMsg('authMessage');
}
function showView(id){['authView','activationView','app'].forEach(x=>document.getElementById(x)?.classList.add('hidden'));document.getElementById(id)?.classList.remove('hidden');}
async function backToLogin(){
 try{await api('cancel_pending');}catch(_){}
 sessionStorage.removeItem('pending_username');
 showView('authView');
 showAuthForm('login');
}
function setActivationState(hasPayment,message=''){
 const title=document.querySelector('#activationView .brand h1');
 const subtitle=document.querySelector('#activationView .brand p');
 const form=document.querySelector('#activationView form');
 const input=document.getElementById('mpesaCode');
 const button=form?.querySelector('button[type="submit"]');
 if(hasPayment){
  if(title) title.textContent='Payment Under Review';
  if(subtitle) subtitle.textContent='Your payment reference has been submitted. Wait for an administrator to activate your account.';
  if(form) form.classList.add('hidden');
  if(message) msg('activationMessage',message,'success');
 }else{
  if(title) title.textContent='Activate Your Account';
  if(subtitle) subtitle.textContent='Make the registration payment, then submit your M-Pesa confirmation code.';
  if(form) form.classList.remove('hidden');
  if(input) input.value='';
  if(button) button.disabled=false;
  clearMsg('activationMessage');
 }
}
async function handleRegistration(e){
 e.preventDefault();
 try{
  const d=await api('register',{
   username:document.getElementById('registerUsername').value.trim().toLowerCase(),
   fullName:document.getElementById('registerName').value.trim(),
   phone:document.getElementById('registerPhone').value.trim(),
   password:document.getElementById('registerPassword').value,
   referral:document.getElementById('registerReferral').value.trim().toLowerCase()
  });
  sessionStorage.setItem('pending_username',d.username);
  showView('activationView');
  setActivationState(false);
 }catch(e){msg('authMessage',e.message);}
}
async function handlePaymentSubmission(e){
 e.preventDefault();
 const button=e.target.querySelector('button[type="submit"]');
 if(button)button.disabled=true;
 try{
  await api('submit_payment',{mpesaCode:document.getElementById('mpesaCode').value.trim().toUpperCase()});
  setActivationState(true,'Payment reference submitted successfully. Your account is now waiting for admin approval.');
 }catch(e){
  if(button)button.disabled=false;
  msg('activationMessage',e.message);
 }
}
async function handleLogin(e){
 e.preventDefault();
 try{
  const d=await api('login',{username:document.getElementById('loginUsername').value.trim().toLowerCase(),password:document.getElementById('loginPassword').value});
  if(d.status==='pending'){
   sessionStorage.setItem('pending_username',d.username||document.getElementById('loginUsername').value.trim().toLowerCase());
   showView('activationView');
   setActivationState(Boolean(d.payment_submitted),d.payment_submitted?'Your payment reference is awaiting admin approval.':'Please submit your M-Pesa payment reference below.');
   return;
  }
  CURRENT_USER=d.user;
  sessionStorage.removeItem('pending_username');
  showApp();
 }catch(e){msg('authMessage',e.message);}
}
async function logout(){try{await api('logout')}catch(_){}CURRENT_USER=null;TEAM={level1:[],level2:[],level3:[]};WITHDRAWALS=[];BALANCE={available:0,earned:0,used:0};sessionStorage.removeItem('pending_username');showView('authView');showAuthForm('login');}
function showApp(){showView('app');document.getElementById('topUsername').textContent=CURRENT_USER.username;document.getElementById('topAvatar').textContent=CURRENT_USER.username.slice(0,2).toUpperCase();showPage('dashboard');}
function toggleSidebar(force){const s=document.getElementById('sidebar'),o=document.getElementById('overlay');const open=typeof force==='boolean'?force:!s.classList.contains('open');s.classList.toggle('open',open);o?.classList.toggle('visible',open);}
async function loadTeam(){const d=await api('team');TEAM=d;BALANCE=d.balance||BALANCE;return d;}
async function loadWithdrawals(){const d=await api('withdrawals');WITHDRAWALS=d.withdrawals||[];BALANCE=d.balance||BALANCE;return WITHDRAWALS;}
function profits(){return Number(BALANCE.earned||0);}
function available(){return Number(BALANCE.available||0);}
async function renderDashboard(){
 await loadTeam();await loadWithdrawals();
 document.getElementById('mainContent').innerHTML=`
 <section class="hero"><h1>Welcome, ${esc(CURRENT_USER.full_name)} 👋</h1><p>Welcome to your GOLYBRAND AGENCIES agent dashboard.</p><div class="hero-finances"><div class="hero-finance"><div class="hero-finance-label">AVAILABLE BALANCE</div><div class="hero-finance-value">Ksh ${money(available())}</div></div></div></section>
 <section class="stats-grid"><article class="stat-card balance-card"><div class="stat-label">Available Balance</div><div class="stat-value">Ksh ${money(available())}</div><div class="stat-sub">Current withdrawable balance</div></article></section>
 <section class="section"><div class="section-title"><h2>Quick Access</h2><span>Explore GOLYBRAND</span></div><div class="quick-grid">
 <article class="quick-card" onclick="showPage('trivia')"><div class="quick-icon">■</div><h3>Trivia Questions</h3><p>Test your knowledge.</p></article>
 <article class="quick-card" onclick="showPage('forex')"><div class="quick-icon">■</div><h3>Forex Classes</h3><p>Learn every week.</p></article>
 <article class="quick-card" onclick="showPage('ebooks')"><div class="quick-icon">■</div><h3>E-books</h3><p>Grow your knowledge.</p></article>
 <article class="quick-card" onclick="showPage('tiktok')"><div class="quick-icon">■</div><h3>TikTok Videos</h3><p>Watch featured content.</p></article>
 <article class="quick-card" onclick="showPage('awards')"><div class="quick-icon">■</div><h3>Best Agent Awards</h3><p>Celebrate top agents.</p></article>
 <article class="quick-card" onclick="showPage('team')"><div class="quick-icon">■</div><h3>My Team</h3><p>Manage your referral network.</p></article>
 <article class="quick-card" onclick="showPage('withdraw')"><div class="quick-icon">■</div><h3>Withdraw</h3><p>Withdraw your available balance.</p></article>
 </div></section>`;
}
function levelCard(n,amount,list){return `<article class="content-card"><div class="section-title"><h2>Level ${n}</h2><span>Ksh ${money(list.length*amount)}</span></div><p>${list.length} member(s) • Ksh ${amount} per activated referral</p>${list.length?'<div class="content-list">'+list.map(u=>`<div class="content-item"><div class="content-item-icon">GA</div><div><strong>${esc(u.username)}</strong><span>${esc(u.full_name)} • ${esc(u.phone)}</span></div></div>`).join('')+'</div>':'<div class="empty">No activated members at this level.</div>'}</article>`}
async function renderTeam(){
 await loadTeam();
 const link=`${location.origin}${location.pathname}?ref=${encodeURIComponent(CURRENT_USER.username)}`;
 document.getElementById('mainContent').innerHTML=`<section class="content-card"><h2>My Team 👥</h2><p>Build your team and earn bonuses from activated referrals.</p><div class="referral-box"><div class="referral-label">YOUR REFERRAL LINK</div><div class="referral-row"><input id="refLink" class="referral-input" value="${esc(link)}" readonly><button class="copy-btn" onclick="copyReferral()">Copy</button></div></div></section><section class="section levels">${levelCard(1,500,TEAM.level1)}${levelCard(2,300,TEAM.level2)}${levelCard(3,100,TEAM.level3)}</section><section class="content-card"><h2>Team Earnings</h2><div class="stat-value">Ksh ${money(profits())}</div><p>Your dashboard displays only the available balance; team earnings are shown here for transparency.</p></section>`;
}
async function renderWithdraw(){
 await loadTeam();await loadWithdrawals();
 document.getElementById('mainContent').innerHTML=`<section class="content-card"><h2>Withdraw 💸</h2><p>Withdraw funds from your available withdrawable balance.</p><div class="withdraw-info"><div class="withdraw-balance-label">Available Withdrawable Balance</div><div class="withdraw-balance">Ksh ${money(available())}</div><div class="withdraw-note">Minimum withdrawal is Ksh 500.</div></div><div id="withdrawMessage"></div><form onsubmit="requestWithdrawal(event)"><div class="form-group"><label>M-Pesa Phone Number</label><input id="withdrawPhone" class="input" type="tel" value="${esc(CURRENT_USER.phone)}" required></div><div class="form-group"><label>Withdrawal Amount</label><input id="withdrawAmount" class="input" type="number" min="500" step="1" max="${Math.floor(available())}" required></div><button class="btn btn-success" type="submit" ${available()<500?'disabled':''}>Request Withdrawal</button></form><div class="withdraw-history"><div class="section-title"><h2>Withdrawal History</h2></div>${WITHDRAWALS.length?WITHDRAWALS.map(w=>`<div class="withdraw-item"><div class="withdraw-item-left"><strong>Ksh ${money(w.amount)}</strong><span>${esc(w.phone)} • ${esc(w.created_at)}</span></div><span class="withdraw-status ${esc(w.status)}">${esc(w.status)}</span></div>`).join(''):'<div class="empty">No withdrawal requests yet.</div>'}</div></section>`;
}
async function requestWithdrawal(e){
 e.preventDefault();
 try{
  await api('withdraw',{phone:document.getElementById('withdrawPhone').value.trim(),amount:Number(document.getElementById('withdrawAmount').value)});
  await renderWithdraw();
 }catch(e){msg('withdrawMessage',e.message);}
}
async function renderManagedContent(page){
 const d=await api('content');
 const typeMap={forex:'forex',ebooks:'ebook',tiktok:'tiktok',awards:'award'};
 const type=typeMap[page];
 if(page==='trivia'){
  const qs=d.trivia||[];
  document.getElementById('mainContent').innerHTML=`<section class="content-card"><h2>Trivia Questions 🧠</h2><p>Answer the questions added by the GOLYBRAND administrator.</p>${qs.length?qs.map((q,i)=>`<article class="trivia-item"><h3>${i+1}. ${esc(q.question)}</h3><div class="trivia-options"><button onclick="answerTrivia(this,'${esc(q.correct_option)}')">A. ${esc(q.option_a)}</button><button onclick="answerTrivia(this,'${esc(q.correct_option)}')">B. ${esc(q.option_b)}</button><button onclick="answerTrivia(this,'${esc(q.correct_option)}')">C. ${esc(q.option_c)}</button><button onclick="answerTrivia(this,'${esc(q.correct_option)}')">D. ${esc(q.option_d)}</button></div><div class="trivia-result"></div><small>${esc(q.explanation||'')}</small></article>`).join(''):'<div class="empty">No trivia questions have been published yet.</div>'}</section>`;
  return;
 }
 const items=(d.items||[]).filter(x=>x.content_type===type);
 const titles={forex:'Weekly Forex Classes',ebooks:'E-books',tiktok:'TikTok Videos',awards:'Best Agent Awards'};
 document.getElementById('mainContent').innerHTML=`<section class="content-card"><h2>${titles[page]}</h2><p>Resources published by the GOLYBRAND administrator.</p>${items.length?items.map(x=>`<article class="content-item"><div><h3>${esc(x.title)}</h3><p>${esc(x.description||'')}</p>${x.url?`<a class="btn" href="${esc(x.url)}" target="_blank" rel="noopener">Open Resource</a>`:''}</div></article>`).join(''):'<div class="empty">No content has been published yet.</div>'}</section>`;
}
function answerTrivia(btn,correct){const wrap=btn.closest('.trivia-item');const result=wrap.querySelector('.trivia-result');const letter=btn.textContent.trim().charAt(0);wrap.querySelectorAll('button').forEach(b=>b.disabled=true);result.textContent=letter===correct?'Correct!':'Incorrect. The correct answer is '+correct+'.';}
async function contentPage(title,text){return renderManagedContent(({ 'Trivia Questions':'trivia','Weekly Forex Classes':'forex','E-books':'ebooks','TikTok Videos':'tiktok','Best Agent Awards':'awards'})[title]||title);}
async function showPage(page){
 toggleSidebar(false);
 document.querySelectorAll('.nav-list button').forEach(b=>b.classList.remove('active'));
 const map={dashboard:'navDashboard',team:'navTeam',withdraw:'navWithdraw'};
 if(map[page])document.getElementById(map[page])?.classList.add('active');
 try{
  if(page==='dashboard')return await renderDashboard();
  if(page==='team')return await renderTeam();
  if(page==='withdraw')return await renderWithdraw();
  const titles={trivia:'Trivia Questions',forex:'Weekly Forex Classes',ebooks:'E-books',tiktok:'TikTok Videos',awards:'Best Agent Awards'};
  if(['trivia','forex','ebooks','tiktok','awards'].includes(page)) return renderManagedContent(page);
  return contentPage(titles[page]||page,'Explore GOLYBRAND AGENCIES learning and agent resources.');
 }catch(e){alert(e.message);}
}
async function copyReferral(){
 const e=document.getElementById('refLink');
 try{await navigator.clipboard.writeText(e.value);alert('Referral link copied.');}
 catch(_){e.select();document.execCommand('copy');alert('Referral link copied.');}
}
async function init(){
 try{
  const d=await api('session');
  if(d.user){CURRENT_USER=d.user;sessionStorage.removeItem('pending_username');showApp();return;}
  if(d.pending && sessionStorage.getItem('pending_username')){
   sessionStorage.setItem('pending_username',d.pending_username||sessionStorage.getItem('pending_username'));
   showView('activationView');
   setActivationState(Boolean(d.payment_submitted),d.payment_submitted?'Your payment reference is awaiting admin approval.':'Please submit your M-Pesa payment reference below.');
   return;
  }
  showView('authView');
  const p=new URLSearchParams(location.search);
  if(p.get('ref')){showAuthForm('register');document.getElementById('registerReferral').value=p.get('ref').toLowerCase();}
 }catch(e){showView('authView');msg('authMessage',e.message);}
}
window.addEventListener('load',init);
