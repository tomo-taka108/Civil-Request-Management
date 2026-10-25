/**
 * 無地の PNG 画像をその場で作る（画像ファイルをリポジトリに置かないため）。
 *
 * 用途は2つ。
 * - 地図タイルの代わりに返すダミー画像（設計書 5章）
 * - 写真アップロードのテストで送る画像（サーバーはファイルの中身で形式を判定するため、
 *   拡張子だけ .png にした偽物ではなく、ブラウザが表示できる本物の PNG にする）
 */

import { crc32, deflateSync } from 'node:zlib';

function chunk(type: string, data: Buffer): Buffer {
  const length = Buffer.alloc(4);
  length.writeUInt32BE(data.length);
  const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(body));
  return Buffer.concat([length, body, crc]);
}

/** 指定した大きさ・色の無地 PNG を返す。 */
export function solidPng(width: number, height: number, [r, g, b]: [number, number, number]): Buffer {
  const header = Buffer.alloc(13);
  header.writeUInt32BE(width, 0);
  header.writeUInt32BE(height, 4);
  header[8] = 8; // 1色あたり8ビット
  header[9] = 2; // RGB
  // 圧縮方式・フィルタ方式・インターレースはすべて0（標準）

  // 各行の先頭にフィルタ種別（0＝なし）を置き、そのあとに画素を並べる。
  const row = Buffer.alloc(1 + width * 3);
  for (let x = 0; x < width; x++) {
    row[1 + x * 3] = r;
    row[2 + x * 3] = g;
    row[3 + x * 3] = b;
  }
  const pixels = Buffer.concat(Array.from({ length: height }, () => row));

  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', header),
    chunk('IDAT', deflateSync(pixels)),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}
