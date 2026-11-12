import test from 'node:test';
import assert from 'node:assert/strict';
import { matchesSearch } from '../../resources/js/utils/search.js';

for (const [text, query, expected] of [
    ['Nicolò D’Angelo', 'nicolo dangelo', true],
    ['Nicolò D’Angelo', 'angelo nicolo', true],
    ['ONE-TEAM S.r.l.', 'oneteam srl', true],
    ['ONE-TEAM', 'one team', true],
    ['Giulia Grandi', 'grandi giulia', true],
    ['LU3G_Hub', 'lu3g hub', true],
    ['Caffè', 'caffe\u0300', true],
    [['Luigi Pistone', 'luigi.pistone@lu3g.it'], 'pistone lu3g', true],
    [['Compenso', 'Gennaio 2026'], '2026 compenso', true],
    ['Studio Æ Ø Ł', 'ae o l', true],
    ['東京', '東京', true],
    ['Nicolò D’Angelo', 'nicolo rossi', false],
    [null, 'nicolo', false],
    ['Anything', '', true],
    ['Anything', '%_*', true],
]) {
    test(`${JSON.stringify(query)} in ${JSON.stringify(text)}`, () => assert.equal(matchesSearch(text, query), expected));
}
