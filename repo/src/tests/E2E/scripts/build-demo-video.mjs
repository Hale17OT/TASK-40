import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const rootDir = process.cwd();
const resultsDir = path.join(rootDir, 'test-results');
const outputDir = path.join(rootDir, 'test-results', 'demo-video');
const concatListPath = path.join(outputDir, 'concat-list.txt');
const outputVideoPath = path.join(outputDir, 'harborbite-demo.mp4');

function collectWebmFiles(dirPath, out = []) {
  if (!fs.existsSync(dirPath)) {
    return out;
  }

  const entries = fs.readdirSync(dirPath, { withFileTypes: true });
  for (const entry of entries) {
    const fullPath = path.join(dirPath, entry.name);
    if (entry.isDirectory()) {
      collectWebmFiles(fullPath, out);
    } else if (entry.isFile() && entry.name.endsWith('.webm')) {
      out.push(fullPath);
    }
  }

  return out;
}

function run() {
  const ffmpegCheck = spawnSync('ffmpeg', ['-version'], { stdio: 'ignore', shell: true });
  if (ffmpegCheck.status !== 0) {
    console.error('ffmpeg is required to stitch videos. Install ffmpeg and rerun npm run demo:build.');
    process.exit(1);
  }

  const clips = collectWebmFiles(resultsDir).sort((a, b) => a.localeCompare(b));
  if (clips.length === 0) {
    console.error('No Playwright .webm clips found in test-results/. Run npm run demo:record first.');
    process.exit(1);
  }

  fs.mkdirSync(outputDir, { recursive: true });

  const concatFileBody = clips
    .map((clipPath) => {
      const normalized = clipPath.replace(/\\/g, '/').replace(/'/g, "'\\''");
      return `file '${normalized}'`;
    })
    .join('\n');

  fs.writeFileSync(concatListPath, concatFileBody, 'utf8');

  const ffmpegArgs = [
    '-y',
    '-f',
    'concat',
    '-safe',
    '0',
    '-i',
    concatListPath,
    '-c:v',
    'libx264',
    '-pix_fmt',
    'yuv420p',
    '-movflags',
    '+faststart',
    outputVideoPath,
  ];

  const ffmpegRun = spawnSync('ffmpeg', ffmpegArgs, { stdio: 'inherit', shell: true });
  if (ffmpegRun.status !== 0) {
    console.error('Failed to build stitched demo video.');
    process.exit(1);
  }

  console.log(`Created demo video: ${outputVideoPath}`);
  console.log(`Clips included: ${clips.length}`);
}

run();
