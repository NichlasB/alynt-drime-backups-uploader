// Fixed runtime selection only. No arbitrary executable, service restart or site bootstrap.
import { readFileSync, readdirSync, realpathSync } from 'node:fs';

export function resolveOwnedStdioRuntime(target) {
	if (process.platform !== 'win32') throw Error('Windows proof only.');
	if (target === 'standalone') return 'C:/php/php.exe';
	if (target !== 'plugin-tester') throw Error('Unsupported owned stdio runtime.');
	const base = 'C:/Users/Captain/AppData/Roaming/Local';
	const sites = Object.values(JSON.parse(readFileSync(`${base}/sites.json`, 'utf8'))).filter((s) => s.domain === 'plugin-tester.local');
	if (sites.length !== 1 || realpathSync(`${sites[0].path}/app/public`).replaceAll('\\', '/') !== 'C:/Users/Captain/Local Sites/plugin-tester/app/public' ||
		Number(sites[0].services?.mariadb?.ports?.MYSQL?.[0]) !== 10004 || !/^\d+\.\d+\.\d+$/.test(sites[0].services?.php?.version)) throw Error('Local target metadata mismatch.');
	const services = `${base}/lightning-services`, version = sites[0].services.php.version;
	const matches = readdirSync(services).filter((name) => new RegExp(`^php-${version.replaceAll('.', '\\.')}\\+\\d+$`).test(name));
	if (matches.length !== 1) throw Error('Ambiguous bundled PHP runtime.');
	const php = `${services}/${matches[0]}/bin/win64/php.exe`;
	if (realpathSync(php).replaceAll('\\', '/') !== php) throw Error('Unexpected bundled PHP runtime.');
	return php;
}
