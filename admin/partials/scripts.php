<script>
/* ===== NAVBAR DROPDOWN ===== */
function toggleNavDropdown(e) {
  e.stopPropagation();
  document.getElementById('nav-dropdown').classList.toggle('open');
}
document.addEventListener('click', function(e) {
  const dd = document.getElementById('nav-dropdown');
  if (dd && !e.target.closest('.dropdown-wrap')) dd.classList.remove('open');
});

/* ===== ANIMATED NODE NETWORK BACKGROUND ===== */
(function() {
  const canvas = document.getElementById('tech-bg');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let w, h;
  const NODE_COUNT = 65, MAX_DIST = 130;
  function resize() { w = canvas.width = window.innerWidth; h = canvas.height = window.innerHeight; }
  window.addEventListener('resize', resize); resize();
  const nodes = Array.from({length:NODE_COUNT}, () => ({x:Math.random()*w,y:Math.random()*h,vx:(Math.random()-.5)*.45,vy:(Math.random()-.5)*.45}));
  const mouse = {x:null,y:null};
  window.addEventListener('mousemove', e=>{mouse.x=e.clientX;mouse.y=e.clientY;});
  window.addEventListener('mouseleave',()=>{mouse.x=null;mouse.y=null;});
  let running = true;
  document.addEventListener('visibilitychange',()=>{running=!document.hidden;if(running)draw();});
  function draw() {
    if(!running) return;
    ctx.clearRect(0,0,w,h);
    nodes.forEach(n=>{
      if(mouse.x!==null){const dx=mouse.x-n.x,dy=mouse.y-n.y,d=Math.hypot(dx,dy);if(d<180){n.vx+=dx/d*.012;n.vy+=dy/d*.012;}}
      n.x+=n.vx;n.y+=n.vy;n.vx*=.99;n.vy*=.99;
      if(n.x<0||n.x>w)n.vx*=-1;if(n.y<0||n.y>h)n.vy*=-1;
      ctx.fillStyle='rgba(56,189,248,0.9)';ctx.beginPath();ctx.arc(n.x,n.y,2.2,0,Math.PI*2);ctx.fill();
    });
    for(let i=0;i<nodes.length;i++){for(let j=i+1;j<nodes.length;j++){const d=Math.hypot(nodes[i].x-nodes[j].x,nodes[i].y-nodes[j].y);if(d<MAX_DIST){ctx.strokeStyle=`rgba(56,189,248,${(1-d/MAX_DIST)*.6})`;ctx.lineWidth=.8;ctx.beginPath();ctx.moveTo(nodes[i].x,nodes[i].y);ctx.lineTo(nodes[j].x,nodes[j].y);ctx.stroke();}}}
    requestAnimationFrame(draw);
  }
  draw();
})();
</script>
