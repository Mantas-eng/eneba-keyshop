import React, {useEffect, useState} from 'react';
import {createRoot} from 'react-dom/client';

const money = cents => new Intl.NumberFormat('en-IE',{style:'currency',currency:'EUR'}).format(cents/100);
const art = id => '/art/'+id+'.jpg';
async function api(route, body) {
  const response = await fetch('/api.php?r='+route, body ? {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)} : {});
  const data = await response.json();
  if (!response.ok) throw new Error(data.error || 'Request failed.');
  return data;
}
function useData(route) {
  const [result,set] = useState({});
  useEffect(()=>{
    let active=true;
    api(route).then(data=>active && set({route,data})).catch(e=>active && set({route,error:e.message}));
    return ()=>{active=false};
  },[route]);
  return result.route===route ? result : {};
}
function Status({result}) {return <p role="status">{result.error || 'Loading…'}</p>}
function Cards({games}) {
  return <div className="grid">{games.map(g=><a className="card" key={g.id} href={'#game/'+g.id}>
    <img src={art(g.id)} alt=""/><div><small>{g.platform} · {g.region}</small><h3>{g.name}</h3><span>From <b>{money(g.price)}</b></span></div>
  </a>)}</div>;
}
function Home() {
  const banner=useData('banner'), shelves=useData('shelves');
  return <>{banner.data ? <section className="hero" style={{backgroundImage:'linear-gradient(90deg,#170c30 0%,#170c30bd 35%,transparent 82%),url('+banner.data.image+')'}}>
    <small>{banner.data.subtitle}</small><h1>{banner.data.title}</h1><a className="cta" href={'#game/'+banner.data.game}>Explore offers →</a></section> : <Status result={banner}/>}
    {shelves.data ? shelves.data.map(s=><section key={s.id}><div className="line"><h2>{s.title}</h2><a href="#store">View store →</a></div><Cards games={s.games}/></section>) : <Status result={shelves}/>}</>;
}
function Store({query}) {
  const result=useData('catalog&q='+encodeURIComponent(query));
  const [platform,setPlatform]=useState(''),[region,setRegion]=useState(''),[sort,setSort]=useState('name');
  const games=(result.data || []).filter(g=>(!platform || platform===g.platform) && (!region || region===g.region));
  if (sort!=='name') games.sort((a,b)=>sort==='low'?a.price-b.price:b.price-a.price);
  return <><h1>{query?'Results for “'+query+'”':'Game store'}</h1><div className="filters">
    <label>Platform<select value={platform} onChange={e=>setPlatform(e.target.value)}><option value="">All platforms</option><option>Steam</option><option>Xbox</option></select></label>
    <label>Region<select value={region} onChange={e=>setRegion(e.target.value)}><option value="">All regions</option><option>Europe</option><option>Global</option></select></label>
    <label>Sort by<select value={sort} onChange={e=>setSort(e.target.value)}><option value="name">Name A–Z</option><option value="low">Lowest price</option><option value="high">Highest price</option></select></label></div>
    {!result.data?<Status result={result}/>:games.length?<Cards games={games}/>:<p>No games match. Try another name or filter.</p>}</>;
}
function Game({id,add}) {
  const result=useData('game&id='+encodeURIComponent(id)), g=result.data;
  if (!g) return <Status result={result}/>;
  return <><a href="#store">← Game store</a><div className="detail"><img src={art(g.id)} alt={g.name}/><div><small>{g.platform} · {g.region}</small><h1>{g.name}</h1><p>{g.description}</p></div></div><h2>Seller offers</h2>
    {g.offers.map(o=><div className="row" key={o.id}><strong>{o.seller}</strong><b>{money(o.cents)}</b><button onClick={()=>add({...o,name:g.name})}>Add to cart</button></div>)}</>;
}
function Account({onLogin}) {
  const [register,set]=useState(false),[error,setError]=useState('');
  async function submit(e) {
    e.preventDefault();
    try {const user=await api(register?'register':'login',Object.fromEntries(new FormData(e.target))); onLogin(user.email);}
    catch(e){setError(e.message)}
  }
  return <form className="panel" onSubmit={submit}><h1>{register?'Create account':'Sign in'}</h1><label>Email<input type="email" name="email" autoComplete="email" required/></label><label>Password<input type="password" name="password" autoComplete={register?'new-password':'current-password'} minLength={register?8:1} required/></label><button>{register?'Create account':'Sign in'}</button><button type="button" className="link" onClick={()=>{set(!register);setError('')}}>{register?'Already registered? Sign in':'New here? Create account'}</button><p role="alert">{error}</p></form>;
}
function Orders() {
  const r=useData('orders');
  return <><h1>Your orders</h1>{!r.data?<Status result={r}/>:r.data.length?r.data.map(i=><article className="receipt" key={i.license}><small>Order #{i.id} · {i.created} UTC</small><h3>{i.name} · {money(i.paid)}</h3><p>{i.seller}</p><code>{i.license}</code></article>):<p>No purchases yet.</p>}</>;
}
function getPage(){return location.hash.slice(1) || 'home'}
function App(){
  const [page,setPage]=useState(getPage),[email,setEmail]=useState(undefined),[search,setSearch]=useState('');
  const [cart,setCart]=useState(()=>{try{return JSON.parse(localStorage.getItem('basket')) || {items:[]}}catch{return {items:[]}}});
  const [busy,setBusy]=useState(false),[error,setError]=useState('');
  useEffect(()=>{api('session').then(u=>setEmail(u.email)).catch(e=>setError(e.message));
    function change(){setPage(getPage());setError('');window.scrollTo(0,0)}
    window.addEventListener('hashchange',change);return ()=>window.removeEventListener('hashchange',change);
  },[]);
  const [path,param='']=page.split('/');
  const query=new URLSearchParams(page.split('?')[1]).get('q') || '';
  useEffect(()=>{setSearch(query)},[query]);
  function update(items){const next={items,token:crypto.randomUUID()};localStorage.setItem('basket',JSON.stringify(next));setCart(next)}
  async function checkout(){
    setBusy(true);setError('');
    try{await api('checkout',{offers:cart.items.map(o=>o.id),token:cart.token});update([]);location.hash='orders'}
    catch(e){setError(e.message)}finally{setBusy(false)}
  }
  return <><header><div className="bar"><a className="brand" href="#home"><i>◈</i> keyshop</a><form className="search" onSubmit={e=>{e.preventDefault();location.hash='store?q='+encodeURIComponent(search)}}><input aria-label="Search games" placeholder="Search for games" value={search} onChange={e=>setSearch(e.target.value)}/><button aria-label="Submit search">Search</button></form><a href="#cart">Cart ({cart.items.length})</a></div></header>
    <nav><div className="bar"><a href="#home">Discover</a><a href="#store">Game store</a><span/>{email?<><a href="#orders">My orders</a><button className="link" onClick={async()=>{try{await api('logout',{});setEmail(null);location.hash='home'}catch(e){setError(e.message)}}}>Sign out</button></>:<a href="#account">Sign in</a>}</div></nav>
    <main>{error && <p role="alert">{error}</p>}
    {page==='home'?<Home/>:page.split('?')[0]==='store'?<Store query={query}/>:path==='game'?<Game id={param} add={o=>{update([...cart.items,o]);location.hash='cart'}}/>:
    path==='account'?<Account onLogin={v=>{setEmail(v);location.hash=cart.items.length?'cart':'orders'}}/>:
    path==='orders'?email===undefined?<p>Loading account…</p>:email?<Orders/>:<a href="#account">Sign in to see your orders</a>:
    path==='cart'?<><h1>Your cart</h1>{cart.items.length?<>{cart.items.map((o,i)=><div className="row" key={i}><div><strong>{o.name}</strong><small>{o.seller}</small></div><b>{money(o.cents)}</b><button className="link" disabled={busy} onClick={()=>update(cart.items.filter((_,j)=>j!==i))}>Remove</button></div>)}<div className="total"><h2>Total {money(cart.items.reduce((sum,o)=>sum+o.cents,0))}</h2><p>Simulated payment. Purchases deliver demo keys.</p>{email?<button disabled={busy} onClick={checkout}>{busy?'Processing…':'Pay & receive keys'}</button>:<a className="cta" href="#account">Sign in to checkout</a>}</div></>:<p>Your cart is empty. <a href="#store">Browse games →</a></p>}</>:<a href="#home">Return home</a>}</main></>;
}
createRoot(document.getElementById('root')).render(<App/>);

