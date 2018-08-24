<?php

namespace App\Http\Controllers\Vendor;

use App\File;
use App\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\UnauthorizedException;

class NewProductController extends Controller
{
    public function showDetailsStep()
    {
        return view('vendor.new-product.details-step');
    }
    public function processDetailsStep(Request $request)
    {
        $this->validate($request, [
            'title' => 'required', // todo: specify min and max for product title
            'body' => 'required',
            'price' => 'required|numeric|min:0', // todo: specify min and max for product price
            'category_id' => 'required|exists:categories,id'
        ]);

        session()->put('new_product.details_step', $request->only([
            'title',
            'body',
            'price',
            'category_id'
        ]));

        return redirect('/vendor/new-product/cover');
    }

    public function showCoverStep()
    {
        if (! session()->has('new_product.details_step'))
        {
            return redirect('/vendor/new-product/');
        }

        return view('vendor.new-product.cover-step');
    }

    public function processCoverStep(Request $request)
    {
        // todo: If should be done should be consistent in other methods too
        if (! session()->has('new_product.details_step'))
        {
            return redirect('/vendor/new-product');
        }

        $this->validate($request, [
            'cover' => 'required|file|mimes:jpeg|dimensions:min_width=640,ratio=4/3'
        ]);

        // todo: resize every uploaded cover to a fix size

        $path = with($file = $request->file('cover'))->store('product_covers', 'public');

        $cover = File::createFromUploadedFile($file, [
            'path' => $path,
            'assoc' => 'cover',
        ]);

        session()->put('new_product.cover_step.file_id', $cover->id);

        return redirect('/vendor/new-product/sample');
    }

    public function showSampleStep()
    {
        return view('vendor.new-product.sample-step');
    }

    public function processSampleStep(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|file'
        ]);

        $path = with($file = $request->file('file'))->store('product_samples', 'public');

        $file = File::createFromUploadedFile($file, [
            'assoc' => 'sample',
            'path' => $path,
        ]);

        session()->put('new_product.sample_step.file_id', $file->id);

        return redirect('/vendor/new-product/product-file');
    }

    public function showProductFileStep()
    {
        return view('vendor.new-product.product-file-step');
    }

    public function processProductFileStep(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|file'
        ]);

        $path = with($file = $request->file('file'))->store('product_files', 'local');

        $file = File::createFromUploadedFile($file, [
            'assoc' => 'product',
            'path' => $path,
        ]);

        session()->put('new_product.product_file_step.file_id', $file->id);

        return redirect('/vendor/new-product/confirmation');
    }

    public function showConfirmationStep()
    {
        return __('New Product').' &raquo; '.__('Confirmation');
    }

    public function processConfirmationStep()
    {
        DB::transaction(function () {
            $product = auth()->user()->products()->create(
                session('new_product.details_step')
            );

            // consider failing the process if a file not found
            $files = File::find([
                session('new_product.cover_step.file_id'),
                session('new_product.sample_step.file_id'),
                session('new_product.product_file_step.file_id'),
            ]);

            foreach ($files as $file) {
                $file->update([
                    'product_id' => $product->id
                ]);
            }
        });

        session()->remove('new_product');

        return redirect('/vendor/products');
    }
}
