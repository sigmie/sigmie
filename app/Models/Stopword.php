<?php

declare(strict_types=1);

namespace App\Models;

use Sushi\Sushi;

class Stopword extends Model
{
    use Sushi;

    protected $rows = [
        [
            'id' => 1,
            'word' => 'about',
        ],
        [
            'id' => 2,
            'word' => 'above',
        ],
        [
            'id' => 3,
            'word' => 'after',
        ],
        [
            'id' => 4,
            'word' => 'again',
        ],
        [
            'id' => 5,
            'word' => 'against',
        ],
        [
            'id' => 6,
            'word' => 'all',
        ],
        [
            'id' => 7,
            'word' => 'am',
        ],
        [
            'id' => 8,
            'word' => 'an',
        ],
        [
            'id' => 9,
            'word' => 'and',
        ],
        [
            'id' => 10,
            'word' => 'any',
        ],
        [
            'id' => 11,
            'word' => 'are',
        ],
        [
            'id' => 12,
            'word' => 'aren',
        ],
        [
            'id' => 13,
            'word' => 'as',
        ],
        [
            'id' => 14,
            'word' => 'at',
        ],
        [
            'id' => 15,
            'word' => 'be',
        ],
        [
            'id' => 16,
            'word' => 'because',
        ],
        [
            'id' => 17,
            'word' => 'been',
        ],
        [
            'id' => 18,
            'word' => 'before',
        ],
        [
            'id' => 19,
            'word' => 'being',
        ],
        [
            'id' => 20,
            'word' => 'below',
        ],
        [
            'id' => 21,
            'word' => 'between',
        ],
        [
            'id' => 22,
            'word' => 'both',
        ],
        [
            'id' => 23,
            'word' => 'but',
        ],
        [
            'id' => 24,
            'word' => 'by',
        ],
        [
            'id' => 25,
            'word' => 'can',
        ],
        [
            'id' => 26,
            'word' => 'cannot',
        ],
        [
            'id' => 27,
            'word' => 'could',
        ],
        [
            'id' => 28,
            'word' => 'couldn',
        ],
        [
            'id' => 29,
            'word' => 'd',
        ],
        [
            'id' => 30,
            'word' => 'did',
        ],
        [
            'id' => 31,
            'word' => 'didn',
        ],
        [
            'id' => 32,
            'word' => 'do',
        ],
        [
            'id' => 33,
            'word' => 'does',
        ],
        [
            'id' => 34,
            'word' => 'doesn',
        ],
        [
            'id' => 35,
            'word' => 'doing',
        ],
        [
            'id' => 36,
            'word' => 'don',
        ],
        [
            'id' => 37,
            'word' => 'done',
        ],
        [
            'id' => 38,
            'word' => 'down',
        ],
        [
            'id' => 39,
            'word' => 'during',
        ],
        [
            'id' => 40,
            'word' => 'each',
        ],
        [
            'id' => 41,
            'word' => 'few',
        ],
        [
            'id' => 42,
            'word' => 'fewer',
        ],
        [
            'id' => 43,
            'word' => 'for',
        ],
        [
            'id' => 44,
            'word' => 'from',
        ],
        [
            'id' => 45,
            'word' => 'further',
        ],
        [
            'id' => 46,
            'word' => 'had',
        ],
        [
            'id' => 47,
            'word' => 'hadn',
        ],
        [
            'id' => 48,
            'word' => 'has',
        ],
        [
            'id' => 49,
            'word' => 'hasn',
        ],
        [
            'id' => 50,
            'word' => 'have',
        ],
        [
            'id' => 51,
            'word' => 'haven',
        ],
        [
            'id' => 52,
            'word' => 'having',
        ],
        [
            'id' => 53,
            'word' => 'he',
        ],
        [
            'id' => 54,
            'word' => 'her',
        ],
        [
            'id' => 55,
            'word' => 'here',
        ],
        [
            'id' => 56,
            'word' => 'hers',
        ],
        [
            'id' => 57,
            'word' => 'herself',
        ],
        [
            'id' => 58,
            'word' => 'him',
        ],
        [
            'id' => 59,
            'word' => 'himself',
        ],
        [
            'id' => 60,
            'word' => 'his',
        ],
        [
            'id' => 61,
            'word' => 'how',
        ],
        [
            'id' => 62,
            'word' => 'i',
        ],
        [
            'id' => 63,
            'word' => 'if',
        ],
        [
            'id' => 64,
            'word' => 'in',
        ],
        [
            'id' => 65,
            'word' => 'into',
        ],
        [
            'id' => 66,
            'word' => 'is',
        ],
        [
            'id' => 67,
            'word' => 'isn',
        ],
        [
            'id' => 68,
            'word' => 'it',
        ],
        [
            'id' => 69,
            'word' => 'its',
        ],
        [
            'id' => 70,
            'word' => 'itself',
        ],
        [
            'id' => 71,
            'word' => 'll',
        ],
        [
            'id' => 72,
            'word' => 'many',
        ],
        [
            'id' => 73,
            'word' => 'may',
        ],
        [
            'id' => 74,
            'word' => 'me',
        ],
        [
            'id' => 75,
            'word' => 'might',
        ],
        [
            'id' => 76,
            'word' => 'mine',
        ],
        [
            'id' => 77,
            'word' => 'more',
        ],
        [
            'id' => 78,
            'word' => 'most',
        ],
        [
            'id' => 79,
            'word' => 'must',
        ],
        [
            'id' => 80,
            'word' => 'mustn',
        ],
        [
            'id' => 81,
            'word' => 'my',
        ],
        [
            'id' => 82,
            'word' => 'myself',
        ],
        [
            'id' => 83,
            'word' => 'no',
        ],
        [
            'id' => 84,
            'word' => 'none',
        ],
        [
            'id' => 85,
            'word' => 'nor',
        ],
        [
            'id' => 86,
            'word' => 'not',
        ],
        [
            'id' => 87,
            'word' => 'of',
        ],
        [
            'id' => 88,
            'word' => 'off',
        ],
        [
            'id' => 89,
            'word' => 'on',
        ],
        [
            'id' => 90,
            'word' => 'only',
        ],
        [
            'id' => 91,
            'word' => 'or',
        ],
        [
            'id' => 92,
            'word' => 'other',
        ],
        [
            'id' => 93,
            'word' => 'ought',
        ],
        [
            'id' => 94,
            'word' => 'our',
        ],
        [
            'id' => 95,
            'word' => 'ours',
        ],
        [
            'id' => 96,
            'word' => 'ourselves',
        ],
        [
            'id' => 97,
            'word' => 'out',
        ],
        [
            'id' => 98,
            'word' => 'over',
        ],
        [
            'id' => 99,
            'word' => 's',
        ],
        [
            'id' => 100,
            'word' => 'shall',
        ],
        [
            'id' => 101,
            'word' => 'shan',
        ],
        [
            'id' => 102,
            'word' => 'she',
        ],
        [
            'id' => 103,
            'word' => 'should',
        ],
        [
            'id' => 104,
            'word' => 'shouldn',
        ],
        [
            'id' => 105,
            'word' => 'so',
        ],
        [
            'id' => 106,
            'word' => 'some',
        ],
        [
            'id' => 107,
            'word' => 'such',
        ],
        [
            'id' => 108,
            'word' => 't',
        ],
        [
            'id' => 109,
            'word' => 'than',
        ],
        [
            'id' => 110,
            'word' => 'the',
        ],
        [
            'id' => 111,
            'word' => 'their',
        ],
        [
            'id' => 112,
            'word' => 'theirs',
        ],
        [
            'id' => 113,
            'word' => 'them',
        ],
        [
            'id' => 114,
            'word' => 'themselves',
        ],
        [
            'id' => 115,
            'word' => 'then',
        ],
        [
            'id' => 116,
            'word' => 'there',
        ],
        [
            'id' => 117,
            'word' => 'these',
        ],
        [
            'id' => 118,
            'word' => 'they',
        ],
        [
            'id' => 119,
            'word' => 'this',
        ],
        [
            'id' => 120,
            'word' => 'those',
        ],
        [
            'id' => 121,
            'word' => 'through',
        ],
        [
            'id' => 122,
            'word' => 'to',
        ],
        [
            'id' => 123,
            'word' => 'too',
        ],
        [
            'id' => 124,
            'word' => 'under',
        ],
        [
            'id' => 125,
            'word' => 'until',
        ],
        [
            'id' => 126,
            'word' => 'up',
        ],
        [
            'id' => 127,
            'word' => 've',
        ],
        [
            'id' => 128,
            'word' => 'very',
        ],
        [
            'id' => 129,
            'word' => 'was',
        ],
        [
            'id' => 130,
            'word' => 'wasn',
        ],
        [
            'id' => 131,
            'word' => 'we',
        ],
        [
            'id' => 132,
            'word' => 'where',
        ],
        [
            'id' => 133,
            'word' => 'were',
        ],
        [
            'id' => 134,
            'word' => 'weren',
        ],
        [
            'id' => 135,
            'word' => 'what',
        ],
        [
            'id' => 136,
            'word' => 'when',
        ],
        [
            'id' => 137,
            'word' => 'where',
        ],
        [
            'id' => 138,
            'word' => 'which',
        ],
        [
            'id' => 139,
            'word' => 'while',
        ],
        [
            'id' => 140,
            'word' => 'who',
        ],
        [
            'id' => 141,
            'word' => 'whom',
        ],
        [
            'id' => 142,
            'word' => 'why',
        ],
        [
            'id' => 143,
            'word' => 'with',
        ],
        [
            'id' => 144,
            'word' => 'without',
        ],
        [
            'id' => 145,
            'word' => 'won',
        ],
        [
            'id' => 146,
            'word' => 'would',
        ],
        [
            'id' => 147,
            'word' => 'wouldn',
        ],
        [
            'id' => 148,
            'word' => 'you',
        ],
        [
            'id' => 149,
            'word' => 'your',
        ],
        [
            'id' => 150,
            'word' => 'yours',
        ],
        [
            'id' => 151,
            'word' => 'yourself',
        ],
        [
            'id' => 152,
            'word' => 'yourselves',
        ],
    ];
}
