// Invented complete packets, never a native capture or transport.
export function packet(payload, sequence) {
	const body = Buffer.from(payload), header = Buffer.alloc(4);
	header.writeUIntLE(body.length, 0, 3); header[3] = sequence;
	return Buffer.concat([header, body]);
}
