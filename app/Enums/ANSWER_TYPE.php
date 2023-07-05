<?php

namespace App\Enums;

enum ANSWER_TYPE: string {
    case ONE = "one";
    case MANY = "many";
    case FREE = "free";
    case SCALE = "scale";
    case QUESTIONS = "questions";
}