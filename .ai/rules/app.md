---
paths:
  - 'app/**'
---

# App

Use single responsibility every where :

example :

```php
    Route::get('proucts', ListProductController::class);
    Route::post('proucts', StoreProductController::class);
    Route::get('proucts/{product}', ShowProductController::class);
    Route::put('proucts/{product}', UpdateProductController::class);
    Route::delete('proucts/{product}', DeleteProductController::class);
```

# if need extra logic than use service class
