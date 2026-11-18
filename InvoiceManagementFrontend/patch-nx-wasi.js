const fs = require('fs');
const path = require('path');

const targetFile = path.join(__dirname, 'node_modules', 'nx', 'dist', 'src', 'native', 'nx.wasi.cjs');

if (!fs.existsSync(targetFile)) {
  console.log('[patch-nx-wasi] Target file not found, skipping patch.');
  process.exit(0);
}

let content = fs.readFileSync(targetFile, 'utf8');

const dynamicInit = `function __napi_rs_initialize_modules(__napiInstance) {
  for (const k of Object.keys(__napiInstance.exports)) {
    if (k.startsWith('__napi_register__') && typeof __napiInstance.exports[k] === 'function') {
      try {
        __napiInstance.exports[k]();
      } catch (e) {
        // ignore
      }
    }
  }
}`;

if (content.includes('for (const k of Object.keys(__napiInstance.exports))')) {
  console.log('[patch-nx-wasi] File is already patched.');
  process.exit(0);
}

const regex = /function __napi_rs_initialize_modules\(__napiInstance\)\s*\{[\s\S]*?\n\}/;
if (regex.test(content)) {
  content = content.replace(regex, dynamicInit);
  fs.writeFileSync(targetFile, content, 'utf8');
  console.log('[patch-nx-wasi] Successfully patched nx.wasi.cjs!');
} else {
  console.warn('[patch-nx-wasi] Could not find function __napi_rs_initialize_modules to patch.');
}
