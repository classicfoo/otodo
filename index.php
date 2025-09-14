<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = get_db();
$stmt = $db->prepare('SELECT id, description, due_date, details, done, priority FROM tasks WHERE user_id = :uid AND done = 0 ORDER BY due_date IS NULL, due_date, priority DESC, id DESC');

$stmt->execute([':uid' => $_SESSION['user_id']]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
$priority_labels = [0 => 'None', 1 => 'Low', 2 => 'Medium', 3 => 'High'];
$priority_classes = [0 => 'text-secondary', 1 => 'text-success', 2 => 'text-warning', 3 => 'text-danger'];

$tz = $_SESSION['location'] ?? 'UTC';
try {
    $tzObj = new DateTimeZone($tz);
} catch (Exception $e) {
    $tzObj = new DateTimeZone('UTC');
}
$today = new DateTime('today', $tzObj);
$tomorrow = (clone $today)->modify('+1 day');
$todayFmt = $today->format('Y-m-d');
$tomorrowFmt = $tomorrow->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .due-date-badge { display: inline-block; width: 100px; text-align: centre; }
        .priority-text { display: inline-block; width: 70px; text-align: center; }
    </style>
    <title>Todo List</title>
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <span class="navbar-brand mb-0 h1">Otodo</span>
        <span id="navStatus" class="badge bg-secondary d-none">Offline ⨯</span>
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#menu" aria-controls="menu">
            <span class="navbar-toggler-icon"></span>
        </button>
    </div>
</nav>

<div class="offcanvas offcanvas-start" tabindex="-1" id="menu" aria-labelledby="menuLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="menuLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="mb-3">
            <span id="netStatus" class="badge bg-secondary">Offline ⨯</span>
            <button id="checkNow" class="btn btn-sm btn-outline-secondary ms-2">Check now</button>
            <div id="pendingInfo" class="small mt-2"></div>
        </div>
        <p class="mb-4">Hello, <?=htmlspecialchars($_SESSION['username'] ?? '')?></p>
        <div class="list-group">
            <a href="index.php" class="list-group-item list-group-item-action">Active Tasks</a>
            <a href="completed.php" class="list-group-item list-group-item-action">Completed Tasks</a>
            <a href="settings.php" class="list-group-item list-group-item-action">Settings</a>
            <a href="logout.php" class="list-group-item list-group-item-action">Logout</a>
        </div>
    </div>
</div>
<div class="container">
    <form action="add_task.php" method="post" class="mb-3">
        <div class="input-group">
            <input type="text" name="description" class="form-control" placeholder="New task" required autocapitalize="none">
            <button class="btn btn-primary" type="submit">Add</button>
        </div>
    </form>
    <div id="taskList" class="list-group"></div>
</div>
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
  <div id="toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-body"></div>
  </div>
</div>
<script>
  window.initialTasks = <?=json_encode($tasks)?>;
  window.defaultPriority = <?=json_encode((int)($_SESSION['default_priority'] ?? 0))?>;
  window.userTimeZone = <?=json_encode($tz)?>;
  window.priorityLabels = <?=json_encode($priority_labels)?>;
  window.priorityClasses = <?=json_encode($priority_classes)?>;
</script>
<script src="sw-register.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const taskListEl = document.getElementById('taskList');
  const form = document.querySelector('form');
  const navStatus = document.getElementById('navStatus');
  const netStatus = document.getElementById('netStatus');
  const checkNowBtn = document.getElementById('checkNow');
  const pendingInfo = document.getElementById('pendingInfo');
  const toastEl = document.getElementById('toast');
  const toastBody = toastEl.querySelector('.toast-body');
  const toast = new bootstrap.Toast(toastEl);

  let tasks = [];
  let queue = [];

  function normalize(str){return str.toLowerCase().replace(/\b\w/g,c=>c.toUpperCase());}

  function loadState(){
    try{tasks=JSON.parse(localStorage.getItem('tasks'))||[];}catch(e){tasks=[];}
    try{queue=JSON.parse(localStorage.getItem('queue'))||[];}catch(e){queue=[];}
    if(tasks.length===0){tasks=window.initialTasks||[];}
  }
  function saveState(){
    localStorage.setItem('tasks', JSON.stringify(tasks));
    localStorage.setItem('queue', JSON.stringify(queue));
  }

  function computeDueInfo(due){
    if(!due) return {text:'', class:'bg-secondary-subtle text-secondary'};
    const today=new Date().toLocaleDateString('en-CA',{timeZone:window.userTimeZone});
    const tomorrow=new Date(Date.now()+86400000).toLocaleDateString('en-CA',{timeZone:window.userTimeZone});
    if(due<today) return {text:'Overdue',class:'bg-danger-subtle text-danger'};
    if(due===today) return {text:'Today',class:'bg-success-subtle text-success'};
    if(due===tomorrow) return {text:'Tomorrow',class:'bg-primary-subtle text-primary'};
    return {text:'Later',class:'bg-primary-subtle text-primary'};
  }

  function render(){
    taskListEl.innerHTML='';
    tasks.forEach(t=>{
      const item=document.createElement('div');
      item.className='list-group-item d-flex justify-content-between align-items-center';
      const left=document.createElement('div');
      left.className='d-flex align-items-center gap-2';
      const checkbox=document.createElement('input');
      checkbox.type='checkbox';
      checkbox.className='form-check-input';
      checkbox.checked=!!t.done;
      checkbox.addEventListener('change',()=>toggleTask(t.id, checkbox.checked));
      left.appendChild(checkbox);
      const span=document.createElement('span');
      span.textContent=t.description||'';
      span.style.cursor='pointer';
      if(t.done) span.classList.add('text-decoration-line-through');
      span.addEventListener('click',()=>editTask(t.id));
      left.appendChild(span);
      const right=document.createElement('div');
      right.className='d-flex align-items-center gap-2';
      if(t.due_date){
        const info=computeDueInfo(t.due_date);
        const badge=document.createElement('span');
        badge.className='badge due-date-badge '+info.class;
        badge.textContent=info.text;
        right.appendChild(badge);
      } else {
        const badge=document.createElement('span');
        badge.className='due-date-badge';
        right.appendChild(badge);
      }
      const pr=document.createElement('span');
      const p=t.priority||0;
      pr.className='small priority-text '+window.priorityClasses[p];
      pr.textContent=window.priorityLabels[p];
      right.appendChild(pr);
      const del=document.createElement('button');
      del.className='btn btn-sm btn-link text-danger';
      del.textContent='×';
      del.addEventListener('click',()=>deleteTask(t.id));
      right.appendChild(del);
      if(queue.some(op=>op.id===t.id)){
        const clock=document.createElement('span');
        clock.textContent='⏰';
        right.appendChild(clock);
      }
      item.appendChild(left);
      item.appendChild(right);
      taskListEl.appendChild(item);
    });
    pendingInfo.textContent=queue.length?`Changes waiting to sync (${queue.length})`:'';
  }

  function addTask(desc){
    const id='tmp-'+Date.now();
    const today=new Date().toLocaleDateString('en-CA',{timeZone:window.userTimeZone});
    const t={id,description:normalize(desc),done:0,priority:window.defaultPriority,due_date:today};
    tasks.push(t);
    queue.push({type:'add',id,task:t});
    saveState();
    render();
  }

  function toggleTask(id,done){
    const t=tasks.find(x=>x.id===id); if(!t)return; t.done=done?1:0;
    const addOp=queue.find(o=>o.type==='add'&&o.id===id);
    if(addOp){addOp.task.done=t.done;}else{
      let op=queue.find(o=>o.type==='update'&&o.id===id);
      if(op){op.task.done=t.done;} else {queue.push({type:'update',id,task:{done:t.done}});}
    }
    saveState();
    render();
  }

  function editTask(id){
    const t=tasks.find(x=>x.id===id); if(!t)return;
    const desc=prompt('Edit task',t.description); if(desc===null)return;
    t.description=normalize(desc.trim());
    const addOp=queue.find(o=>o.type==='add'&&o.id===id);
    if(addOp){addOp.task.description=t.description;} else {
      let op=queue.find(o=>o.type==='update'&&o.id===id);
      if(op){op.task.description=t.description;} else {queue.push({type:'update',id,task:{description:t.description}});}
    }
    saveState();
    render();
  }

  function deleteTask(id){
    tasks=tasks.filter(t=>t.id!==id);
    const addIdx=queue.findIndex(o=>o.type==='add'&&o.id===id);
    if(addIdx!==-1){queue.splice(addIdx,1);}else{
      queue=queue.filter(o=>!(o.type==='update'&&o.id===id));
      queue.push({type:'delete',id});
    }
    saveState();
    render();
  }

  function showToast(msg,retry=false){
    toastBody.innerHTML=msg;
    toast.show();
    if(retry){
      const btn=document.getElementById('retryNow');
      if(btn) btn.addEventListener('click',()=>{toast.hide();sync();});
    }
  }

  async function ping(){
    try{await fetch('sw-register.js',{method:'HEAD',cache:'no-store'});return true;}catch(e){return false;}
  }

  function updateStatus(online){
    const on = online !== undefined ? online : navigator.onLine;
    const text = on ? 'Online ✓' : 'Offline ⨯';
    const cls = on ? 'badge bg-success' : 'badge bg-secondary';
    navStatus.className = cls;
    navStatus.textContent = text;
    navStatus.classList.remove('d-none');
    netStatus.className = cls;
    netStatus.textContent = text;
  }

  async function checkNow(){
    const online = await ping();
    updateStatus(online);
    if(online) await sync();
  }

  async function sync(){
    if(!navigator.onLine || queue.length===0){
      if(queue.length===0) showToast('All changes saved');
      updateStatus();
      return;
    }
    try{
      for(let i=0;i<queue.length;){
        const op=queue[i];
        if(op.type==='add'){
          const data=new URLSearchParams({description:op.task.description});
          const res=await fetch('add_task.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:data});
          if(!res.ok) throw new Error();
          const json=await res.json();
          const newId=json.id;
          tasks.find(t=>t.id===op.id).id=newId;
          queue.forEach(o=>{if(o.id===op.id) o.id=newId;});
          queue.splice(i,1);
        } else if(op.type==='update'){
          const t=tasks.find(x=>x.id===op.id); if(!t) {queue.splice(i,1); continue;}
          const data=new URLSearchParams({description:t.description,due_date:t.due_date||'',details:t.details||'',priority:t.priority||0,done:t.done?1:0});
          const res=await fetch('task.php?id='+op.id,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:data});
          if(!res.ok) throw new Error();
          await res.json();
          queue.splice(i,1);
        } else if(op.type==='delete'){
          const res=await fetch('delete_task.php?id='+op.id,{headers:{'X-Requested-With':'XMLHttpRequest'}});
          if(!res.ok) throw new Error();
          await res.json();
          queue.splice(i,1);
        }
      }
      saveState();
      render();
      showToast('All changes saved');
    }catch(e){
      saveState();
      render();
      showToast("Couldn't sync. Will retry when online. <button class='btn btn-link p-0' id='retryNow'>Retry now</button>", true);
    }
  }

  form.addEventListener('submit',e=>{e.preventDefault();const d=form.description.value.trim();if(!d)return;addTask(d);form.reset();});

  checkNowBtn.addEventListener('click',checkNow);
  document.getElementById('menu').addEventListener('show.bs.offcanvas',checkNow);
  window.addEventListener('online',sync);

  loadState();
  render();
  updateStatus();
  if(navigator.onLine) sync();
})();
</script>
</body>
</html>
