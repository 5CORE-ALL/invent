import RunwayML, { TaskFailedError } from '@runwayml/sdk';
import { readFileSync } from 'node:fs';

if (!process.env.RUNWAYML_API_SECRET) {
  process.stderr.write(JSON.stringify({ error: 'missing_secret' }));
  process.exit(2);
}

const raw = readFileSync(0, 'utf8');
let body;
try {
  body = JSON.parse(raw);
} catch {
  process.stderr.write(JSON.stringify({ error: 'invalid_json' }));
  process.exit(2);
}

const client = new RunwayML();

try {
  const task = await client.recipes.productAd(body).waitForTaskOutput();
  const output = Array.isArray(task.output) ? task.output : [];
  process.stdout.write(JSON.stringify({
    id: task.id ?? null,
    status: task.status ?? null,
    output,
  }));
} catch (error) {
  if (error instanceof TaskFailedError) {
    const details = error.taskDetails ?? {};
    process.stderr.write(JSON.stringify({
      error: 'task_failed',
      id: details.id ?? null,
      failure: details.failure ?? null,
      failureCode: details.failureCode ?? null,
    }));
    process.exit(1);
  }

  process.stderr.write(JSON.stringify({
    error: 'request_failed',
    message: error instanceof Error ? error.message : 'Product Ad request failed',
  }));
  process.exit(1);
}
