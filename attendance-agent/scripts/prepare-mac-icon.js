const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const assets = path.join(__dirname, '..', 'assets');
const source = path.join(assets, 'icon-256.png');
const dest = path.join(assets, 'icon-mac.png');

if (!fs.existsSync(source)) {
    console.error('Missing ' + source + '. Run the Windows icon script or add assets/icon-256.png.');
    process.exit(1);
}

execFileSync('sips', ['-z', '1024', '1024', source, '--out', dest], { stdio: 'inherit' });

const size = execFileSync('sips', ['-g', 'pixelWidth', '-g', 'pixelHeight', dest], { encoding: 'utf8' });
if (!/pixelWidth: 1024/.test(size) || !/pixelHeight: 1024/.test(size)) {
    console.error('Mac icon must be 1024x1024. sips reported:\n' + size);
    process.exit(1);
}

console.log('Wrote ' + dest);
