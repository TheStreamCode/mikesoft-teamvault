import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const SUCCESS_MESSAGE = 'Success: Checks complete. No errors found.';
const FINDING_TYPES = new Set(['ERROR', 'WARNING']);

function collectItems(data, file) {
  if (!Array.isArray(data)) {
    throw new Error('Plugin Check JSON output must be a JSON array of findings');
  }
  if (data.length === 0) {
    throw new Error('Plugin Check JSON output contains no findings');
  }

  return data.map((item, index) => {
    if (!item || typeof item !== 'object' || Array.isArray(item)) {
      throw new Error(`Plugin Check finding ${index + 1} must be a JSON object`);
    }

    if (typeof item.type !== 'string' || !FINDING_TYPES.has(item.type)) {
      throw new Error(`Plugin Check finding ${index + 1} must have type ERROR or WARNING`);
    }
    if (
      !Number.isInteger(item.line) ||
      item.line < 0 ||
      !Number.isInteger(item.column) ||
      item.column < 0 ||
      typeof item.code !== 'string' ||
      !item.code ||
      typeof item.message !== 'string' ||
      !item.message ||
      typeof item.docs !== 'string'
    ) {
      throw new Error(
        `Plugin Check finding ${index + 1} must include line, column, code, message, and docs`,
      );
    }

    return { ...item, file, type: item.type };
  });
}

function parseJson(value) {
  try {
    return JSON.parse(value);
  } catch {
    throw new Error(
      'Plugin Check output is neither the documented success message nor valid JSON findings',
    );
  }
}

function sanitizeLogValue(value) {
  return String(value ?? '')
    .replace(/[\u0000-\u001f\u007f-\u009f\u2028\u2029]/g, ' ')
    .replaceAll('::', ': :')
    .replaceAll('##[', '# # [');
}

export function parsePluginCheckOutput(raw) {
  const trimmed = raw.trim();

  if (!trimmed) {
    throw new Error('Plugin Check produced no output');
  }

  if (trimmed === SUCCESS_MESSAGE) {
    return [];
  }

  const headers = [...trimmed.matchAll(/^FILE:[ \t]*(.*)\r?$/gm)];
  if (headers.length === 0) {
    throw new Error('Plugin Check findings must use FILE-prefixed JSON blocks');
  }

  if (trimmed.slice(0, headers[0].index).trim()) {
    throw new Error('Plugin Check output contains unexpected text before the first FILE block');
  }

  const items = [];
  for (let index = 0; index < headers.length; index += 1) {
    const header = headers[index];
    const nextHeader = headers[index + 1];
    const file = header[1].trim();
    const block = trimmed.slice(header.index + header[0].length, nextHeader?.index).trim();

    if (!file) {
      throw new Error(`Plugin Check FILE block ${index + 1} has no file name`);
    }
    if (!block) {
      throw new Error(`Plugin Check FILE block for ${file} has no JSON findings`);
    }

    items.push(...collectItems(parseJson(block), file));
  }

  return items;
}

function run() {
  const resultsPath = process.argv[2];
  const checkExitValue = process.argv[3];

  if (!resultsPath) {
    throw new Error('Plugin Check results path is required');
  }
  if (checkExitValue === undefined) {
    throw new Error('Plugin Check exit code is required');
  }
  if (!/^\d+$/.test(checkExitValue)) {
    throw new Error(`Invalid Plugin Check exit code: ${checkExitValue}`);
  }

  const checkExit = Number.parseInt(checkExitValue, 10);
  if (!Number.isSafeInteger(checkExit) || checkExit > 255) {
    throw new Error(`Invalid Plugin Check exit code: ${checkExitValue}`);
  }

  const raw = fs.readFileSync(resultsPath, 'utf8');
  const items = parsePluginCheckOutput(raw);
  const errors = items.filter((item) => item.type === 'ERROR');
  const warnings = items.filter((item) => item.type === 'WARNING');

  console.log(`Plugin Check: ${errors.length} error(s), ${warnings.length} warning(s).`);
  for (const error of errors.slice(0, 50)) {
    console.log(
      `ERROR ${sanitizeLogValue(error.code)} ${sanitizeLogValue(error.file)}:${sanitizeLogValue(error.line)} ${sanitizeLogValue(error.message)}`,
    );
  }

  if (errors.length > 0) {
    throw new Error(`WordPress Plugin Check reported ${errors.length} error(s)`);
  }
  if (checkExit !== 0) {
    throw new Error(`Plugin Check exited ${checkExit} without a parsed error`);
  }

  console.log('Plugin Check passed (no errors).');
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    run();
  } catch (error) {
    console.error(
      `Plugin Check validation failed: ${sanitizeLogValue(error instanceof Error ? error.message : error)}`,
    );
    process.exitCode = 1;
  }
}
