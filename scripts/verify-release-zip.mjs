import { promises as fs } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const pluginSlug = 'alynt-drime-backups-uploader';
const endOfCentralDirectorySignature = 0x06054b50;
const centralDirectoryEntrySignature = 0x02014b50;
const minimumEndRecordSize = 22;
const maximumCommentSize = 0xffff;

function findEndOfCentralDirectory(buffer) {
	const minimumOffset = Math.max(
		0,
		buffer.length - minimumEndRecordSize - maximumCommentSize,
	);

	for (
		let offset = buffer.length - minimumEndRecordSize;
		offset >= minimumOffset;
		offset -= 1
	) {
		if (buffer.readUInt32LE(offset) === endOfCentralDirectorySignature) {
			return offset;
		}
	}

	throw new Error('Release ZIP has no valid end-of-central-directory record.');
}

function readEntryNames(buffer) {
	const endOffset = findEndOfCentralDirectory(buffer);
	const diskNumber = buffer.readUInt16LE(endOffset + 4);
	const centralDirectoryDisk = buffer.readUInt16LE(endOffset + 6);
	const entryCount = buffer.readUInt16LE(endOffset + 10);
	const centralDirectorySize = buffer.readUInt32LE(endOffset + 12);
	const centralDirectoryOffset = buffer.readUInt32LE(endOffset + 16);

	if (
		diskNumber !== 0 ||
		centralDirectoryDisk !== 0 ||
		entryCount === 0xffff ||
		centralDirectorySize === 0xffffffff ||
		centralDirectoryOffset === 0xffffffff
	) {
		throw new Error('Multi-disk and ZIP64 release packages are not supported.');
	}

	const centralDirectoryEnd = centralDirectoryOffset + centralDirectorySize;

	if (
		centralDirectoryOffset > buffer.length ||
		centralDirectoryEnd > endOffset
	) {
		throw new Error('Release ZIP central-directory bounds are invalid.');
	}

	const decoder = new TextDecoder('utf-8', { fatal: true });
	const entryNames = [];
	let offset = centralDirectoryOffset;

	for (let index = 0; index < entryCount; index += 1) {
		if (
			offset + 46 > centralDirectoryEnd ||
			buffer.readUInt32LE(offset) !== centralDirectoryEntrySignature
		) {
			throw new Error('Release ZIP central-directory entry is invalid.');
		}

		const fileNameLength = buffer.readUInt16LE(offset + 28);
		const extraFieldLength = buffer.readUInt16LE(offset + 30);
		const commentLength = buffer.readUInt16LE(offset + 32);
		const entryEnd =
			offset + 46 + fileNameLength + extraFieldLength + commentLength;

		if (entryEnd > centralDirectoryEnd) {
			throw new Error('Release ZIP central-directory entry exceeds its bounds.');
		}

		entryNames.push(
			decoder.decode(buffer.subarray(offset + 46, offset + 46 + fileNameLength)),
		);
		offset = entryEnd;
	}

	if (offset !== centralDirectoryEnd) {
		throw new Error('Release ZIP central-directory size does not match its entries.');
	}

	return entryNames;
}

function assertSafeEntryName(entryName) {
	if (
		entryName.length === 0 ||
		entryName.includes('\\') ||
		entryName.startsWith('/') ||
		/^[A-Za-z]:/.test(entryName)
	) {
		throw new Error(`Release ZIP contains an unsafe path: ${entryName}`);
	}

	const pathSegments = entryName.split('/');

	if (pathSegments.includes('..') || pathSegments.includes('.')) {
		throw new Error(`Release ZIP contains an unsafe path: ${entryName}`);
	}
}

export async function verifyReleaseZip(zipPath) {
	const archive = await fs.readFile(zipPath);
	const entryNames = readEntryNames(archive);
	const uniqueEntryNames = new Set(entryNames);
	const expectedRoot = `${pluginSlug}/`;
	const requiredRunner = `${expectedRoot}server-runner/alynt-backup-runner.php`;
	const requiredEntries = [
		`${expectedRoot}alynt-drime-backups-uploader.php`,
		`${expectedRoot}readme.txt`,
		`${expectedRoot}uninstall.php`,
		`${expectedRoot}assets/admin.js`,
		`${expectedRoot}assets/admin-workspaces.js`,
		`${expectedRoot}assets/admin.css`,
		requiredRunner,
	];
	const forbiddenSourceRoot = `${expectedRoot}server-runner/src/`;
	const forbiddenExactEntries = new Set(
		[
			'.editorconfig',
			'.gitattributes',
			'.gitignore',
			'.phpcs.xml',
			'.phpcs.xml.dist',
			'.phpunit.result.cache',
			'AI_CODING_RULES.md',
			'CHANGELOG.md',
			'PRE_RELEASE_CHECKLIST.md',
			'README.md',
			'composer.json',
			'composer.lock',
			'composer.phar',
			'deploy.example.sh',
			'deploy.sh',
			'implementation-plan.tmp.md',
			'package-lock.json',
			'package.json',
			'phpunit.xml',
			'phpunit.xml.dist',
			'session-context.tmp.md',
			'session-handoff.tmp.md',
		].map((entryName) => `${expectedRoot}${entryName}`),
	);
	const forbiddenRoots = [
		'.agents/',
		'.codex/',
		'.cursor/',
		'.github/',
		'.git/',
		'.idea/',
		'.opencode/',
		'.playwright-mcp/',
		'.vscode/',
		'assets/dist/',
		'assets/src/',
		'build/',
		'coverage/',
		'docs/',
		'node_modules/',
		'playwright-report/',
		'scripts/',
		'server-runner/src/',
		'tests/',
		'vendor/',
	].map((entryName) => `${expectedRoot}${entryName}`);

	if (entryNames.length === 0) {
		throw new Error('Release ZIP contains no entries.');
	}

	if (uniqueEntryNames.size !== entryNames.length) {
		throw new Error('Release ZIP contains duplicate entry names.');
	}

	for (const entryName of entryNames) {
		assertSafeEntryName(entryName);

		if (!entryName.startsWith(expectedRoot)) {
			throw new Error(
				`Release ZIP entry is outside the expected plugin root: ${entryName}`,
			);
		}

		if (
			forbiddenExactEntries.has(entryName) ||
			forbiddenRoots.some((forbiddenRoot) => entryName.startsWith(forbiddenRoot)) ||
			entryName.endsWith('.map') ||
			entryName.endsWith('.tmp.md') ||
			entryName.includes('/.env')
		) {
			throw new Error(`Release ZIP contains a forbidden development path: ${entryName}`);
		}
	}

	for (const requiredEntry of requiredEntries) {
		if (!uniqueEntryNames.has(requiredEntry)) {
			throw new Error(`Release ZIP is missing a required runtime file: ${requiredEntry}`);
		}
	}

	if (entryNames.some((entryName) => entryName.startsWith(forbiddenSourceRoot))) {
		throw new Error(
			`Release ZIP must not contain modular runner source: ${forbiddenSourceRoot}`,
		);
	}

	return {
		entryCount: entryNames.length,
		requiredRunner,
	};
}

const invokedPath = process.argv[1] ? path.resolve(process.argv[1]) : '';
const scriptPath = fileURLToPath(import.meta.url);

if (invokedPath === scriptPath) {
	const zipPath = process.argv[2];

	if (!zipPath) {
		throw new Error(
			'Usage: node scripts/verify-release-zip.mjs <release-zip>',
		);
	}

	const result = await verifyReleaseZip(path.resolve(zipPath));
	console.log(
		`Release ZIP verified: ${result.entryCount} entries; generated runner present; modular source absent.`,
	);
}
