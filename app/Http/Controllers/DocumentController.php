<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Setting;
use App\Models\Fill;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Rap2hpoutre\FastExcel\FastExcel;

class DocumentController extends Controller
{
	/**
	 * Display a listing of the resource.
	 */
	public function index() {
		return inertia('Documents/Index', [
			'documents' => Document::with('user:id,name')->withCount('fills')->latest()->get(),
		]);
	}

	/**
	 * Store a newly created resource in storage.
	 */
	public function store(Request $request) {
		$request->validate([
			'title' => 'required',
			'file' => 'required'
		]);

		$originalFileName = $request->file->getClientOriginalName();
		$filePath = $request->file->store('documents');

		if (!$filePath) return back()->withErrors([
			'file' => 'Unable to save file'
		]);

		$variables = $this->getVariablesFromDocx($filePath);
		if (!$variables || !count($variables)) return back()->withErrors([
			'file' => 'Document has no fields to fill'
		]);

		$newDocument = $request->user()->documents()->create([
			'title' => $request->title,
			'description' => $request->description,
			'filename' => $originalFileName,
			'file' => $filePath,
			'fields' => $variables
		]);

		return back()->with('flash', $newDocument);
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit(Document $document) {
		return inertia('Documents/Show', [
			'document' => $document->load('user:id,name'),
			'fills' => $document->fills()->with('user:id,name')->latest()->get()
		]);
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, Document $document) {
		$request->validate([
			'title' => 'required'
		]);

		$document->update([
			'title' => $request->title,
			'description' => $request->description,
			'deprecated' => $request->deprecated,
		]);

		return back()->with('flash', $document);
	}

	public function updateFields(Request $request, Document $document) {
		$request->validate([
			'fields' => 'required'
		]);

		$updatedDocument = $document->update([
			'fields' => $request->fields
		]);

		return response([
			'success' => $updatedDocument
		]);
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(Document $document) {
		$document->load('fills');
		if (Storage::exists($document->file)) Storage::delete($document->file);
		if (count($document->fills) > 0) {
			foreach ($document->fills as $fill) {
				if (Storage::exists("fills/$fill->generated_file")) Storage::delete("fills/$fill->generated_file");
			}
		}
		$document->delete();
		return response(['success' => true]);
	}

	// settings
	public function settings() {
		return inertia('Settings');
	}
	public function addField(Request $request) {
		$request->validate([
			'id' => 'required',
			'title' => 'required'
		]);

		$fields = json_decode(Setting::where('key', 'fields')->first()?->value ?? "{}");
		$fields->{$request->id} = $request->title;

		Setting::updateOrCreate([
			'key' => 'fields'
		], [
			'value' => json_encode($fields)
		]);

		return back()->with(['success' => true]);
	}
	public function deleteField(Request $request) {
		if (!$request->id) return;

		$fieldsSetting = Setting::where('key', 'fields')->first();

		$fields = json_decode($fieldsSetting->value);
		unset($fields->{$request->id});

		$fieldsSetting->update([
			'value' => json_encode($fields)
		]);

		return back()->with(['success' => true]);
	}

	// find vars
	private function getVariablesFromDocx($path) {
		if (!Storage::exists($path)) return [];

		$zip = new \ZipArchive;

		if ($zip->open(Storage::path($path)) === true) {
			$xmlContent = $zip->getFromName('word/document.xml');
			$zip->close();

			$cleanedContent = preg_replace('/<\/?[^>]+>/', '', $xmlContent);

			preg_match_all('/\$\{(.*?)\}/', $cleanedContent, $matches);

			$variables = $matches[1];

			return array_values(array_unique($variables));
		} else {
			return [];
		}
	}
	public function findVariables(Document $document) {
		return $this->getVariablesFromDocx($document->file);
	}

	// Fills
	public function fills(Request $request) {
		$fills = Fill::filter($request->only('title', 'document', 'by', 'from', 'to'))->latest()->paginate(30)->withQueryString();

		return inertia('Documents/Fills', [
			'fills' => $fills,
			'documents' => Document::get(['id', 'title', 'description', 'deprecated', 'fields', 'filename']),
			'users' => User::get(['id', 'name']),
			'filters' => $request->only('title', 'document', 'by', 'from', 'to')
		]);
	}
	public function fill(Request $request, Document $document) {
		if (!Storage::exists($document->file)) return;

		if ($document->deprecated) {
			return [
				'success' => false,
				'deprecated' => true
			];
		}

		$fill = $document->fills()->create([
			'user_id' => $request->user()->id,
			'note' => $request->note,
			'fill_type' => $request->fill_type,
			'addressData' => $request->addressData,
			'batch' => $request->batch
		]);

		$newFileName = '';

		if ($request->fill_type == 'multiple') {
			$partPaths = [];
			$partIndex = 0;
			foreach($request->batch as $part) {
				$templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor(Storage::path($document->file));
				$templateProcessor->setValues($part);

				$partFileName = "$partIndex - $document->id - $fill->id - $document->filename";
				$partPath = Storage::path('/fills/') . $partFileName;
				$templateProcessor->saveAs($partPath);

				$partIndex++;
				$partPaths[] = [
					'fileName' => $partFileName,
					'filePath' => $partPath
				];
			}

			$newFileName = "$document->id - $fill->id - " . pathinfo($document->filename, PATHINFO_FILENAME) . ".zip";

			$zip = new \ZipArchive;
			if ($zip->open(Storage::path("/fills/$newFileName"), \ZipArchive::CREATE|\ZipArchive::OVERWRITE)) {
				foreach($partPaths as $partFile) {
					$zip->addFile($partFile['filePath'], $partFile['fileName']);
				}
				$zip->close();

				foreach($partPaths as $partFile2) {
					Storage::delete('fills/' . $partFile2['fileName']);
				}
			} else return response(['error' => 'unable to store zip file', 'newfilename' => $newFileName]);
		} else {
			$templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor(Storage::path($document->file));
			$templateProcessor->setValues($request->addressData);

			$newFileName = "$document->id - $fill->id - $document->filename";
			$templateProcessor->saveAs(Storage::path('/fills/') . $newFileName);
		}

		$fill->update([
			'generated_file' => $newFileName
		]);

		return response([
			'success' => true,
			'fill' => $fill
		]);
	}

	public function updateFill(Request $request, Fill $fill) {
		$fill->update([
			'note' => $request->note
		]);

		return back()->with('flash', $fill);
	}
	public function destroyFill(Fill $fill) {
		if (Storage::exists("fills/$fill->generated_file")) Storage::delete("fills/$fill->generated_file");
		$fill->delete();
		return response(['success' => true]);
	}

	// download
	public function downloadFill(Fill $fill) {
        if (!Storage::exists("fills/$fill->generated_file")) return response()->json(['error' => 'File not found.'], 404);

        $filePath = Storage::path("fills/$fill->generated_file");

        return response()->download($filePath, $fill->generated_file);
    }
	public function downloadDocument(Document $document) {
        if (!Storage::exists($document->file)) return response()->json(['error' => 'File not found.'], 404);

        $filePath = Storage::path($document->file);

        return response()->download($filePath, $document->filename);
    }


	// reguster uz
	function ruzSuggestions(Request $request) {
		$text = urlencode($request->q);
		$key = "ruz_suggest_$text";

		$res = [
			'success' => false,
			'cached' => false
		];

		$cachedData = cache($key);

		if ($cachedData) {
			$res['success'] = true;
			$res['items'] = $cachedData;
			$res['cached'] = true;
		} else {
			try {
				$response = Http::get("https://www.registeruz.sk/cruz-public/domain/suggestion/search?query=$text");
				if ($response->ok()) {
					$res['success'] = true;
					$items = $response->json();
					$res['items'] = $items;

					cache([$key => $items], now()->addHours(24));
				} else $res['error'] = $response->status();
			} catch (\Exception $e) {
				$res['error'] = $e->getMessage();
			}
		}

		return response($res);
	}

	function loadRuzData(Request $request) {
		$id = urlencode($request->id);
		$key = "ruz_data_$id";

		$res = [
			'success' => false,
			'cached' => false
		];

		$cachedData = cache($key);

		if ($cachedData) {
			$res['success'] = true;
			$res['info'] = $cachedData;
			$res['cached'] = true;
		} else {
			try {
				$response = Http::get("https://www.registeruz.sk/cruz-public/api/uctovna-jednotka?id=$id");
				if ($response->ok()) {
					$res['success'] = true;
					$info = $response->json();
					$res['info'] = $info;

					cache([$key => $info], now()->addHours(24));
				} else $res['error'] = $response->status();
			} catch (\Exception $e) {
				$res['error'] = $e->getMessage();
			}
		}

		return response($res);
	}

	public function checkRuzVat(Request $request) {
		$key = "vies_dph_$request->dic";
		$cachedData = cache($key);

		$res = [
			'success' => false,
			'valid' => false,
			'cached' => false
		];

		if ($cachedData) {
			$res['success'] = true;
			$res['valid'] = $cachedData;
			$res['cached'] = true;
		} else {
			try {
				$client = new \SoapClient("http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl", ['exceptions' => false]);
				$obj3 = $client->checkVat(array('countryCode' => 'SK','vatNumber' => $request->dic));

				if (isset($obj3->valid)) {
					$res['success'] = true;
					$res['valid'] = $obj3->valid;

					cache([$key => $obj3->valid], now()->addHours(24));
				}
			} catch(\Exception $e) {
				$res['error'] = $e->getMessage();
			}
		}

		return response($res);
	}

	// get fields from XLS
	public function loadFieldsData(Request $request) {
		$collection = (new FastExcel)->import($request->file);
		return response($collection);
	}

	public function getXLSXTemplate(Document $document) {
		$fields = [(array_reduce($document->fields, function ($carry, $item) {
			$carry[$item] = '';
			return $carry;
		}, []))];
		$filename = pathinfo($document->filename, PATHINFO_FILENAME) . ".xlsx";

		return (new FastExcel($fields))->download($filename);
	}
	public function getXLSXTemplateWData(Request $request) {
		$fields = $request->rows;

		return (new FastExcel($fields))->download("batch.xlsx");
	}
}