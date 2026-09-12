<?php

it('reports health', function () {
    $this->get('/up')->assertOk();
});
