<?php

use App\Mcp\Servers\MedicationServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('medication', MedicationServer::class);

// Mcp::web('/mcp/demo', \App\Mcp\Servers\PublicServer::class);