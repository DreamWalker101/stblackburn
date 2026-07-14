<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => true,
    'cookie_samesite' => 'Strict',
]);
if (!isset($_SESSION['pp_auth'])) {
    header('Location: index.php');
    exit;
}
$contentFile = __DIR__ . '/../content.json';
$c = file_exists($contentFile) ? json_decode(file_get_contents($contentFile), true) : [];
function v($val, $default = '') { return htmlspecialchars($val ?? $default, ENT_QUOTES, 'UTF-8'); }
function preview($txt) {
    $t = trim(preg_replace('/\s+/', ' ', $txt));
    if ($t === '') return '(empty)';
    return mb_strlen($t) > 52 ? mb_substr($t, 0, 52) . '…' : $t;
}
function field($path, $label, $value, $hint = '') {
    $long = mb_strlen($value) > 60;
    echo '<div class="field"><label>' . v($label) . '</label>';
    if ($long) {
        echo '<textarea data-field="' . v($path) . '">' . v($value) . '</textarea>';
    } else {
        echo '<input type="text" data-field="' . v($path) . '" value="' . v($value) . '">';
    }
    if ($hint) echo '<p class="hint">' . v($hint) . '</p>';
    echo '</div>';
}
$PRIMARY = '#082a5e';

// Global field labels (curated)
$GLOBAL_LABELS = [
    'phoneDisplay'   => 'Phone (display)',
    'phone'          => 'Phone (raw)',
    'email'          => 'Office email',
    'enrolmentEmail' => 'Enrolment email',
    'address'        => 'Address',
    'addressShort'   => 'Address (short)',
    'officeHours'    => 'Office hours',
    'facebookUrl'    => 'Facebook URL',
    'instagramUrl'   => 'Instagram URL',
    'footerTagline'  => 'Footer tagline',
];
// Page tab display names + order
$PAGE_NAMES = [
    'home' => 'Home', 'ourSchool' => 'Our School', 'curriculum' => 'Curriculum',
    'learningJourney' => 'Learning Journey', 'community' => 'Community',
    'enrolment' => 'Enrolment', 'contact' => 'Contact',
];
$pageData = $c['pages'] ?? [];

// Scan all pages for unique real content images (exclude logos/icons/animated)
$allImages = [];
foreach (glob(__DIR__ . '/../*.html') as $hf) {
    if (preg_match_all('#assets/[A-Za-z0-9_./-]+\.(?:jpg|jpeg|png|webp)#i', file_get_contents($hf), $mm)) {
        foreach ($mm[0] as $u) $allImages[$u] = true;
    }
}
$allImages = array_values(array_filter(array_keys($allImages), function ($u) {
    return !preg_match('#logo|flavicon|favicon|/icon|-icon|wired-lineal|/plugins/|/themes/|/wp-includes/#i', $u);
}));
sort($allImages);
$imageMap = $c['imageMap'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>St Thomas Primary School — Content Editor</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: <?= $PRIMARY ?>; --bg:#f4f5f7; --card:#fff; --line:#e3e6ea; --text:#1c2530; --muted:#6b7682; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); }
    .app { display:flex; min-height:100vh; }
    .side { width:236px; background:var(--primary); color:#fff; flex-shrink:0; display:flex; flex-direction:column; position:sticky; top:0; height:100vh; }
    .side-brand { padding:20px 18px; font-weight:700; font-size:15px; line-height:1.3; border-bottom:1px solid rgba(255,255,255,.15); }
    .side-nav { flex:1; overflow-y:auto; padding:8px 0; }
    .side-nav button { width:100%; text-align:left; background:none; border:0; color:rgba(255,255,255,.85); padding:11px 18px; font-size:14px; cursor:pointer; font-family:inherit; }
    .side-nav button:hover { background:rgba(255,255,255,.08); color:#fff; }
    .side-nav button.active { background:rgba(255,255,255,.16); color:#fff; font-weight:600; box-shadow:inset 3px 0 0 #fff; }
    .side-foot { padding:14px 18px; border-top:1px solid rgba(255,255,255,.15); font-size:13px; }
    .side-foot a { color:#fff; text-decoration:none; opacity:.85; } .side-foot a:hover { opacity:1; }
    .main { flex:1; padding:28px 34px; max-width:840px; }
    .panel { display:none; } .panel.active { display:block; }
    .panel h1 { font-size:22px; margin:0 0 4px; }
    .panel .sub { color:var(--muted); font-size:14px; margin:0 0 22px; }
    .card { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:20px 22px; margin-bottom:18px; }
    .card h2 { font-size:15px; margin:0 0 14px; }
    .field { margin-bottom:16px; } .field:last-child { margin-bottom:0; }
    label { display:block; font-size:13px; font-weight:600; margin-bottom:6px; }
    input[type=text], textarea { width:100%; border:1px solid var(--line); border-radius:7px; padding:9px 11px; font-size:14px; font-family:inherit; color:var(--text); }
    input[type=text]:focus, textarea:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 2px rgba(8,42,94,.15); }
    textarea { min-height:90px; resize:vertical; }
    .hint { font-size:12px; color:var(--muted); margin:5px 0 0; }
    .bar { position:sticky; bottom:0; background:linear-gradient(transparent,var(--bg) 40%); padding:18px 0 4px; display:flex; gap:12px; align-items:center; }
    .btn { background:var(--primary); color:#fff; border:0; border-radius:7px; padding:11px 22px; font-size:14px; font-weight:600; cursor:pointer; font-family:inherit; }
    .btn:disabled { opacity:.5; cursor:default; }
    .btn-sec { background:#fff; color:var(--text); border:1px solid var(--line); }
    .toast { position:fixed; bottom:22px; right:22px; background:#1c2530; color:#fff; padding:12px 18px; border-radius:8px; font-size:14px; opacity:0; transform:translateY(8px); transition:.2s; pointer-events:none; }
    .toast.show { opacity:1; transform:none; } .toast.err { background:#b3261e; }
    .media-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:12px; }
    .media-grid figure { margin:0; border:1px solid var(--line); border-radius:8px; overflow:hidden; background:#fff; }
    .media-grid img { width:100%; height:90px; object-fit:cover; display:block; }
    .media-grid figcaption { font-size:11px; padding:5px 7px; color:var(--muted); word-break:break-all; }
    .email-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--line); font-size:14px; }
    .email-row button { background:none; border:0; color:#b3261e; cursor:pointer; font-size:13px; }
    .inline { display:flex; gap:10px; } .inline input { flex:1; }
  </style>
</head>
<body>
<div class="app">
  <aside class="side">
    <div class="side-brand">St Thomas<br>Content Editor</div>
    <nav class="side-nav" id="nav"></nav>
    <div class="side-foot"><a href="../" target="_blank">View site ↗</a> &nbsp;·&nbsp; <a href="index.php?logout=1">Log out</a></div>
  </aside>

  <main class="main">

    <!-- GLOBAL -->
    <section class="panel" data-panel="global">
      <h1>Global &amp; Contact</h1>
      <p class="sub">Shown across every page (footer &amp; contact bar). Phone and address update live everywhere.</p>
      <div class="card">
        <h2>Contact &amp; footer</h2>
        <?php foreach ($GLOBAL_LABELS as $k => $label) field("global.$k", $label, $c['global'][$k] ?? ''); ?>
      </div>
    </section>

    <!-- PAGE PANELS (data-driven) -->
    <?php foreach ($PAGE_NAMES as $pkey => $pname): if ($pkey === 'contact') continue; ?>
    <section class="panel" data-panel="<?= $pkey ?>">
      <h1><?= v($pname) ?></h1>
      <p class="sub">Editable headings &amp; sections on the <?= v(strtolower($pname)) ?> page. Each field shows its current text.</p>
      <div class="card">
        <?php
        $fields = $pageData[$pkey] ?? [];
        if (!$fields) echo '<p class="hint">No editable sections detected on this page yet.</p>';
        $i = 1;
        foreach ($fields as $k => $val) { field("pages.$pkey.$k", 'Section ' . $i++ . ' — “' . preview($val) . '”', $val); }
        ?>
      </div>
    </section>
    <?php endforeach; ?>

    <!-- CONTACT (sections + map) -->
    <section class="panel" data-panel="contact">
      <h1>Contact</h1>
      <p class="sub">Contact page sections and the embedded map.</p>
      <div class="card">
        <h2>Page sections</h2>
        <?php
        $cf = $pageData['contact'] ?? [];
        if (!$cf) echo '<p class="hint">No editable sections detected.</p>';
        $i = 1;
        foreach ($cf as $k => $val) field("pages.contact.$k", 'Section ' . $i++ . ' — “' . preview($val) . '”', $val);
        ?>
      </div>
      <div class="card">
        <h2>Map</h2>
        <?php field('contact.mapEmbedUrl', 'Google Maps embed URL', $c['contact']['mapEmbedUrl'] ?? '', 'Google Maps → Share → Embed a map → copy the src URL only.'); ?>
      </div>
    </section>

    <!-- IMAGES -->
    <section class="panel" data-panel="images">
      <h1>Images</h1>
      <p class="sub">Replace any image on the site. Pick a new file and it swaps everywhere that image appears (after Save). <?= count($allImages) ?> images found.</p>
      <div class="card">
        <div class="media-grid">
        <?php foreach ($allImages as $u): $cur = $imageMap[$u] ?? $u; ?>
          <figure>
            <img src="../<?= v($cur) ?>" alt="" loading="lazy">
            <figcaption>
              <?= v(basename($u)) ?>
              <input type="hidden" data-imgmap="<?= v($u) ?>" value="<?= v($imageMap[$u] ?? '') ?>">
              <button class="btn btn-sec" style="margin-top:5px;padding:4px 8px;font-size:11px;" type="button" onclick="replaceImage(this)">Replace</button>
            </figcaption>
          </figure>
        <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- MEDIA -->
    <section class="panel" data-panel="media">
      <h1>Media library</h1>
      <p class="sub">Upload images, then copy a URL to paste into any field.</p>
      <div class="card">
        <button class="btn" type="button" onclick="uploadMedia()">Upload images</button>
        <div class="media-grid" id="mediaGrid" style="margin-top:16px;"><p class="hint">Loading…</p></div>
      </div>
    </section>

    <!-- ACCESS -->
    <section class="panel" data-panel="access">
      <h1>Admin access</h1>
      <p class="sub">Emails allowed to log in and receive a one-time code.</p>
      <div class="card">
        <div class="inline" style="margin-bottom:14px;">
          <input type="text" id="newEmail" placeholder="name@example.com">
          <button class="btn" type="button" onclick="addEmail()">Add</button>
        </div>
        <div id="emailList"><p class="hint">Loading…</p></div>
      </div>
    </section>

    <div class="bar">
      <button class="btn" id="saveBtn" onclick="save()">Save changes</button>
      <span class="hint" id="saveHint">Saves the section you're currently viewing.</span>
    </div>
  </main>
</div>

<div class="toast" id="toast"></div>

<script>
const TABS = [
  ['global','Global & Contact'],
<?php foreach ($PAGE_NAMES as $pkey => $pname) echo "  ['".$pkey."','".addslashes($pname)."'],\n"; ?>
  ['images','Images'],['media','Media'],['access','Access'],
];
const nav = document.getElementById('nav');
TABS.forEach(([id,label])=>{
  const b=document.createElement('button');
  b.textContent=label; b.dataset.tab=id; b.onclick=()=>activate(id);
  nav.appendChild(b);
});
function activate(id){
  document.querySelectorAll('.panel').forEach(p=>p.classList.toggle('active',p.dataset.panel===id));
  document.querySelectorAll('#nav button').forEach(b=>b.classList.toggle('active',b.dataset.tab===id));
  document.querySelector('.bar').style.display=(id==='media'||id==='access')?'none':'flex';
  if(id==='media') loadMedia();
  if(id==='access') loadEmails();
}
activate('global');

function toast(msg,err){ const t=document.getElementById('toast'); t.textContent=msg; t.className='toast show'+(err?' err':''); setTimeout(()=>t.className='toast',2200); }
function setDeep(obj,path,value){ const ks=path.split('.'); let o=obj; ks.forEach((k,i)=>{ if(i===ks.length-1) o[k]=value; else { o[k]=o[k]||{}; o=o[k]; } }); }
async function save(){
  const panel=document.querySelector('.panel.active'); if(!panel) return;
  let obj={};
  if(panel.dataset.panel==='images'){
    const map={};
    panel.querySelectorAll('[data-imgmap]').forEach(el=>{ if(el.value) map[el.getAttribute('data-imgmap')]=el.value; });
    obj={imageMap:map};
  } else {
    panel.querySelectorAll('[data-field]').forEach(el=>setDeep(obj,el.getAttribute('data-field'),el.value));
  }
  const btn=document.getElementById('saveBtn'); btn.disabled=true;
  try{
    const r=await fetch('api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(obj)});
    const j=await r.json();
    toast(j.success?'Saved':(j.error||'Save failed'), !j.success);
  }catch(e){ toast('Network error',true); }
  btn.disabled=false;
}
async function loadMedia(){
  const g=document.getElementById('mediaGrid');
  const imgs=await (await fetch('api.php?action=images')).json();
  if(!imgs.length){ g.innerHTML='<p class="hint">No images yet.</p>'; return; }
  g.innerHTML='';
  imgs.forEach(im=>{ const fig=document.createElement('figure');
    fig.innerHTML=`<img src="../${im.url}" alt=""><figcaption>${im.name}<br><button class="btn btn-sec" style="margin-top:5px;padding:4px 8px;font-size:11px;" onclick="navigator.clipboard.writeText('${im.url}');toast('URL copied')">Copy URL</button></figcaption>`;
    g.appendChild(fig); });
}
function replaceImage(btn){
  const fig=btn.closest('figure');
  const input=fig.querySelector('[data-imgmap]');
  const img=fig.querySelector('img');
  const inp=document.createElement('input'); inp.type='file'; inp.accept='image/*';
  inp.onchange=async()=>{
    if(!inp.files[0]) return;
    const fd=new FormData(); fd.append('image',inp.files[0]);
    const j=await (await fetch('api.php?action=upload',{method:'POST',body:fd})).json();
    if(j.success){ input.value=j.url; img.src='../'+j.url; toast('Image set — click Save to apply'); }
    else toast(j.error||'Upload failed',true);
  };
  inp.click();
}
function uploadMedia(){
  const inp=document.createElement('input'); inp.type='file'; inp.accept='image/*'; inp.multiple=true;
  inp.onchange=async()=>{ for(const file of inp.files){ const fd=new FormData(); fd.append('image',file); await fetch('api.php?action=upload',{method:'POST',body:fd}); } toast('Uploaded'); loadMedia(); };
  inp.click();
}
async function loadEmails(){
  const box=document.getElementById('emailList');
  const emails=await (await fetch('api.php?action=get_emails')).json();
  if(!emails.length){ box.innerHTML='<p class="hint">No emails yet — add one above.</p>'; return; }
  box.innerHTML=''; emails.forEach(e=>{ const row=document.createElement('div'); row.className='email-row';
    row.innerHTML=`<span>${e}</span><button onclick="removeEmail('${e}')">Remove</button>`; box.appendChild(row); });
}
async function addEmail(){
  const i=document.getElementById('newEmail'); const email=i.value.trim(); if(!email) return;
  const j=await (await fetch('api.php?action=add_email',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email})})).json();
  if(j.success){ i.value=''; toast('Added'); loadEmails(); } else toast(j.error||'Failed',true);
}
async function removeEmail(email){
  const j=await (await fetch('api.php?action=remove_email',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email})})).json();
  if(j.success){ toast('Removed'); loadEmails(); } else toast(j.error||'Failed',true);
}
</script>
</body>
</html>
