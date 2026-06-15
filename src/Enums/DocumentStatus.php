<?php

namespace CLA\LaravelRag\Enums;

enum DocumentStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case EMPTY = 'empty';
    case FAILED = 'failed';
}
