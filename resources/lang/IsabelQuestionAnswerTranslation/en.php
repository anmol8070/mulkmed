<?php

return [
    'questions' => [
        1 => 'How quickly did your symptoms develop?',
        2 => 'How long have you had your symptoms?',
        3 => 'How have your symptoms changed over the last few hours/days?',
        4 => 'How much pain or discomfort are you in?',
        5 => 'How are your symptoms affecting your daily activities?',
        6 => 'Do you feel better after taking medication?',
        7 => 'Do you have any other serious, long term conditions such as diabetes, cancer, heart condition etc.?',
    ],

    // answers[question_id][option_number] => text
    'answers' => [
        1 => [
            1 => 'Over minutes/hours',
            2 => 'Over days',
            3 => 'Over weeks',
            4 => 'Over months/years',
        ],
        2 => [
            1 => '0-6 days',
            2 => 'Weeks',
            3 => 'Months',
            4 => 'Years',
        ],
        3 => [
            1 => 'Better',
            2 => 'Same',
            3 => 'Worse',
        ],
        4 => [
            1 => 'None',
            2 => 'Mild Discomfort',
            3 => 'Very Uncomfortable',
            4 => 'Unbearable',
        ],
        5 => [
            1 => 'No effect',
            2 => 'Struggling to carry out usual activities',
            3 => 'Unable to carry out usual activities',
        ],
        6 => [
            1 => 'Not taking any',
            2 => 'Yes',
            3 => 'No',
        ],
        7 => [
            1 => 'No',
            2 => 'Yes',
        ],
    ],
];
