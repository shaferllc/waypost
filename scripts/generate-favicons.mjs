import { readFileSync, writeFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';
import pngToIco from 'png-to-ico';
import sharp from 'sharp';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, '..');
const input = join(root, 'public/images/waypost.svg');
const outDir = join(root, 'public');

async function main() {
    const svg = readFileSync(input);

    const png32 = await sharp(svg).resize(32, 32).png().toBuffer();
    const png16 = await sharp(svg).resize(16, 16).png().toBuffer();
    const png180 = await sharp(svg).resize(180, 180).png().toBuffer();

    writeFileSync(join(outDir, 'favicon-32x32.png'), png32);
    writeFileSync(join(outDir, 'apple-touch-icon.png'), png180);

    const ico = await pngToIco([png16, png32]);
    writeFileSync(join(outDir, 'favicon.ico'), ico);

    console.log('Wrote public/favicon.ico, favicon-32x32.png, apple-touch-icon.png');
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
