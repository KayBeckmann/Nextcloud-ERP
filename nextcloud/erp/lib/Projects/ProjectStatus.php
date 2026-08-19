<?php

declare(strict_types=1);

namespace OCA\ERP\Projects;

/** Deckungsgleich mit den Filter-Chips aus dem Mockup (ADR-0010). */
enum ProjectStatus: string {
	case Draft = 'draft';
	case Quote = 'quote';
	case InProgress = 'in_progress';
	case Waiting = 'waiting';
	case Done = 'done';
	case Archived = 'archived';
}
