<?php

test('unknown routes return JSON 404 responses', function () {
    $response = $this->getJson('/api/v1/definitely-not-a-route');

    $response->assertStatus(404);
});
