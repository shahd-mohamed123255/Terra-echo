(function(){
// ---------- noise ----------
function h3(x,y,z){var n=Math.imul(x,374761393)^Math.imul(y,668265263)^Math.imul(z,1274126177);n=Math.imul(n^(n>>>13),1274126177);return((n^(n>>>16))>>>0)/4294967296}
function sm(t){return t*t*(3-2*t)}
function n3(x,y,z){var xi=Math.floor(x),yi=Math.floor(y),zi=Math.floor(z),xf=sm(x-xi),yf=sm(y-yi),zf=sm(z-zi);
function L(a,b,t){return a+(b-a)*t}
return L(L(L(h3(xi,yi,zi),h3(xi+1,yi,zi),xf),L(h3(xi,yi+1,zi),h3(xi+1,yi+1,zi),xf),yf),
L(L(h3(xi,yi,zi+1),h3(xi+1,yi,zi+1),xf),L(h3(xi,yi+1,zi+1),h3(xi+1,yi+1,zi+1),xf),yf),zf)}
function fbm(x,y,z,o){var s=0,a=.5,f=1,t=0;for(var i=0;i<o;i++){s+=a*n3(x*f,y*f,z*f);t+=a;a*=.5;f*=2.03}return s/t}
function mix(a,b,t){return[a[0]+(b[0]-a[0])*t,a[1]+(b[1]-a[1])*t,a[2]+(b[2]-a[2])*t]}

// ---------- planet texture ----------
var W=2048,H=1024,cv=document.createElement('canvas');cv.width=W;cv.height=H;
var cx=cv.getContext('2d'),img=cx.createImageData(W,H),d=img.data;
var deep=[6,24,58],shallow=[20,90,130],sand=[176,160,110],forest=[34,82,44],dry=[140,120,80],rock=[110,104,96],snow=[235,243,250];
var greens=[[96,160,60],[70,135,55],[150,175,70],[190,170,80],[120,95,60],[84,150,70]];
for(var y=0;y<H;y++){var lat=(.5-y/H)*Math.PI,cl=Math.cos(lat),sl=Math.sin(lat);
for(var x=0;x<W;x++){var lon=x/W*Math.PI*2,px=cl*Math.cos(lon),py=sl,pz=cl*Math.sin(lon);
var h=fbm(px*2.1+3,py*2.1,pz*2.1,6),c,al=Math.abs(lat);
if(h<.5){c=mix(deep,shallow,Math.max(0,(h-.4)/.1));if(h>.485)c=mix(c,sand,(h-.485)/.015*.6)}
else{
 var wet=fbm(px*3+9,py*3+9,pz*3,4);
 c=mix(dry,forest,Math.min(1,wet*1.6));
 if(h>.64)c=mix(c,rock,Math.min(1,(h-.64)/.08));
 var farm=fbm(px*4.5+20,py*4.5,pz*4.5,3);
 if(farm>.5&&h<.62&&al<1.05&&al>.05){
  var cs=11,u=x/cs,v=y/cs,ci=Math.floor(u),cj=Math.floor(v),r=h3(ci,cj,7);
  var base=greens[Math.floor(r*greens.length)%greens.length];
  var fu=u-ci,fv=v-cj,edge=(fu<.08||fv<.08);
  c=edge?[60,70,40]:base;
  if(!edge&&r>.62){var dx=fu-.5,dy=fv-.5;c=Math.sqrt(dx*dx+dy*dy)<.46?[110,190,80]:[185,165,105]}
  c=mix(c,[
   c[0]*.9,c[1]*.9,c[2]*.9],h3(x,y,3)*.4);
 }
}
if(al>1.18)c=mix(c,snow,Math.min(1,(al-1.18)/.12));
var i=(y*W+x)*4;d[i]=c[0];d[i+1]=c[1];d[i+2]=c[2];d[i+3]=255}}
cx.putImageData(img,0,0);
var tex=new THREE.CanvasTexture(cv);tex.anisotropy=8;

// ---------- cloud texture ----------
var CW=1024,CH=512,cc=document.createElement('canvas');cc.width=CW;cc.height=CH;
var cctx=cc.getContext('2d'),ci2=cctx.createImageData(CW,CH),cd=ci2.data;
for(var y=0;y<CH;y++){var lat=(.5-y/CH)*Math.PI,cl=Math.cos(lat),sl=Math.sin(lat);
for(var x=0;x<CW;x++){var lon=x/CW*Math.PI*2,v=fbm(cl*Math.cos(lon)*3.2+50,sl*3.2,cl*Math.sin(lon)*3.2,5);
var a=Math.max(0,(v-.52)*3.2);var i=(y*CW+x)*4;cd[i]=cd[i+1]=cd[i+2]=255;cd[i+3]=Math.min(210,a*255)}}
cctx.putImageData(ci2,0,0);var ctex=new THREE.CanvasTexture(cc);

// ---------- scene ----------
var canvas=document.getElementById('c');
var renderer=new THREE.WebGLRenderer({canvas:canvas,antialias:true});
renderer.setPixelRatio(Math.min(devicePixelRatio,2));
var scene=new THREE.Scene(),camera=new THREE.PerspectiveCamera(40,1,.1,100);camera.position.set(0,0,6);
var sun=new THREE.DirectionalLight(0xfff2dd,1.6);sun.position.set(-4,2.5,4);scene.add(sun);
scene.add(new THREE.AmbientLight(0x1a2a44,.9));
var group=new THREE.Group();scene.add(group);
var planet=new THREE.Mesh(new THREE.SphereGeometry(1.7,96,96),new THREE.MeshStandardMaterial({map:tex,roughness:.85,metalness:0}));
group.add(planet);
var clouds=new THREE.Mesh(new THREE.SphereGeometry(1.73,96,96),new THREE.MeshStandardMaterial({map:ctex,transparent:true,depthWrite:false,roughness:1}));
group.add(clouds);
var atmo=new THREE.Mesh(new THREE.SphereGeometry(1.95,64,64),new THREE.ShaderMaterial({
 vertexShader:'varying vec3 n;void main(){n=normalize(normalMatrix*normal);gl_Position=projectionMatrix*modelViewMatrix*vec4(position,1.);}',
 fragmentShader:'varying vec3 n;void main(){float i=pow(.72-dot(n,vec3(0.,0.,1.)),3.);gl_FragColor=vec4(.3,.75,1.,1.)*i*1.4;}',
 side:THREE.BackSide,blending:THREE.AdditiveBlending,transparent:true,depthWrite:false}));
group.add(atmo);
// orbit rings
var rg=new THREE.Group();
[[2.5,.5,.2],[3.0,.9,-.3],[3.6,.3,.6]].forEach(function(r,i){
 var t=new THREE.Mesh(new THREE.TorusGeometry(r[0],.004,8,220),new THREE.MeshBasicMaterial({color:0x4fd8ff,transparent:true,opacity:.35-i*.07}));
 t.rotation.set(Math.PI/2+r[1]*.3,r[2],r[1]);rg.add(t)});
group.add(rg);
// moons
var moons=[];
[[2.5,.13,0xd9a07a,0],[3.0,.2,0x3fa8c8,2],[3.6,.1,0x2a6bd6,4]].forEach(function(m,i){
 var s=new THREE.Mesh(new THREE.SphereGeometry(m[1],32,32),new THREE.MeshStandardMaterial({color:m[2],roughness:.8}));
 s.userData={r:m[0],a:m[3],sp:.25-i*.05,tilt:rg.children[i].rotation.clone()};group.add(s);moons.push(s)});
// stars
var sp=new Float32Array(1800*3);for(var i=0;i<1800;i++){var r=40+Math.random()*30,t=Math.random()*6.283,p=Math.acos(2*Math.random()-1);
sp[i*3]=r*Math.sin(p)*Math.cos(t);sp[i*3+1]=r*Math.sin(p)*Math.sin(t);sp[i*3+2]=r*Math.cos(p)}
var sg=new THREE.BufferGeometry();sg.setAttribute('position',new THREE.BufferAttribute(sp,3));
scene.add(new THREE.Points(sg,new THREE.PointsMaterial({color:0xbfe4ff,size:.12,sizeAttenuation:true})));

function resize(){var w=innerWidth,h=innerHeight;renderer.setSize(w,h,false);camera.aspect=w/h;
if(w/h>1){group.position.set(1.6,.1,0);group.scale.setScalar(1)}else{group.position.set(0,1.1,0);group.scale.setScalar(.62)}
camera.updateProjectionMatrix()}
addEventListener('resize',resize);resize();

// drag rotate
var drag=false,lx=0,ly=0,vy=0,tiltX=.35;
canvas.addEventListener('pointerdown',function(e){drag=true;lx=e.clientX;ly=e.clientY});
addEventListener('pointerup',function(){drag=false});
addEventListener('pointermove',function(e){if(!drag)return;planet.rotation.y+=(e.clientX-lx)*.006;clouds.rotation.y+=(e.clientX-lx)*.006;tiltX=Math.max(-.2,Math.min(.8,tiltX+(e.clientY-ly)*.004));lx=e.clientX;ly=e.clientY});
var reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
var clock=new THREE.Clock();
function loop(){var dt=clock.getDelta();
 if(!drag&&!reduce){planet.rotation.y+=dt*.08;clouds.rotation.y+=dt*.1}
 planet.rotation.x=clouds.rotation.x=tiltX;
 moons.forEach(function(m){if(!reduce)m.userData.a+=dt*m.userData.sp;var u=m.userData;
  m.position.set(Math.cos(u.a)*u.r,Math.sin(u.a)*u.r*.25,Math.sin(u.a)*u.r*.9)});
 renderer.render(scene,camera);requestAnimationFrame(loop)}
loop();
})();