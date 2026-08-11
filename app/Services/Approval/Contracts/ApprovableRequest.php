<?php

namespace App\Services\Approval\Contracts;

/**
 * Marker interface implemented by each concrete business request model (e.g. a future
 * RoleChangeRequest, LevelPromotionRequest). No shared methods are required in Phase D
 * since no concrete workflow exists yet - each workflow's steps() implementation
 * type-hints its own concrete request class internally rather than relying on this
 * interface for data access.
 */
interface ApprovableRequest {}
