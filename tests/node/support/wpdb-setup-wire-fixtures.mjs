// Invented complete packets, never a native capture or transport.
export function packet(payload, sequence) {
	const body = Buffer.from(payload), header = Buffer.alloc(4);
	header.writeUIntLE(body.length, 0, 3); header[3] = sequence;
	return Buffer.concat([header, body]);
}

export const modeColumnHex = '0364656600000012404053455353494f4e2e73716c5f6d6f6465000c2d0000040000fd0000000000';
export function modeFrames(value = '') {
	const row = Buffer.from(value);
	return [Buffer.from([1]), Buffer.from(modeColumnHex, 'hex'), Buffer.from([254, 0, 0, 2, 0]),
		Buffer.concat([Buffer.from([row.length]), row]), Buffer.from([254, 0, 0, 2, 0])].map((body, index) => packet(body, index + 1));
}
