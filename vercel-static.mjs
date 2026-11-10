import { cp, mkdir } from 'node:fs/promises';
await mkdir('dist', {recursive: true});
for (const name of ['app.js','style.css','index.html','art']) {
  await cp('public/'+name, 'dist/'+name, {recursive: true});
}
