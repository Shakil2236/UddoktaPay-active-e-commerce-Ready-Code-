# UddoktaPay module of Active E-Commerce with API V2

1. Just Upload these files on your project & replace with existing files.

2. Go to `routes/web.php` then add these code at the end of the code

```bash
//Uddoktapay Start
Route::controller(App\Http\Controllers\Payment\UddoktapayController::class)->group(function () {
    Route::any('/uddoktapay/success','success')->name('uddoktapay.success');
    Route::any('/uddoktapay/cancel','cancel')->name('uddoktapay.cancel');
    Route::any('/uddoktapay/webhook','webhook')->name('uddoktapay.webhook');
});
//Uddoktapay end
```