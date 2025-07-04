<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\GameList;
use App\Models\Admin\Product;
use App\Models\HotGame;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class GameListController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = GameList::with(['gameType', 'product']);

            return Datatables::of($data)
                ->addIndexColumn() // This will automatically add a column called DT_RowIndex
                ->addColumn('game_type', function ($row) {
                    return $row->gameType->name ?? 'N/A';
                })
                ->addColumn('product', function ($row) {
                    return $row->game_provide_name;
                })
                ->addColumn('status', function ($row) {
                    return $row->status == 1
                        ? '<span class="badge badge-success">Open</span>'
                        : '<span class="badge badge-danger">Close</span>';
                })
                ->addColumn('hot_status', function ($row) {
                    return $row->hot_status == 1
                        ? '<span class="badge badge-info">HotGame</span>'
                        : '<span class="badge badge-warning">NormalGame</span>';
                })
                ->addColumn('action', function ($row) {
                    $btn = '<form action="'.route('admin.gameLists.toggleStatus', $row->id).'" method="POST" style="display:inline;">
                                '.csrf_field().'
                                '.method_field('PATCH').'
                                <button type="submit" class="btn btn-info btn-sm">GameStatus</button>
                            </form>';
                    $btn .= '<form action="'.route('admin.HotGame.toggleStatus', $row->id).'" method="POST" style="display:inline;">
                                '.csrf_field().'
                                '.method_field('PATCH').'
                                <button type="submit" class="btn btn-success btn-sm">HotGame</button>
                            </form>';

                    return $btn;
                })
                ->rawColumns(['action', 'status', 'hot_status'])
                ->make(true);

        }


        $gameName = request()->game_name;


        $query = GameList::with(['gameType', 'product']);
        if($gameName != null) {
                $query->where(function($query) use($gameName) {
                    $query->where('name','like','%'.$gameName.'%');
                });
        }

        $gameLists = $query->paginate(100);


        return view('admin.game_list.paginate_index',compact('gameLists'));
    }

    public function edit($gameTypeId, $productId)
    {
        $gameType = GameList::with([
            'products' => function ($query) use ($productId) {
                $query->where('products.id', $productId);
            },
        ])->where('id', $gameTypeId)->first();

        return view('admin.game_type.edit', compact('gameType', 'productId'));
    }

    public function update(Request $request, $gameTypeId, $productId)
    {
        $image = $request->file('image');
        $ext = $image->getClientOriginalExtension();
        $filename = uniqid('game_type').'.'.$ext;
        $image->move(public_path('assets/img/game_logo/'), $filename);

        DB::table('game_type_product')->where('game_type_id', $gameTypeId)->where('product_id', $productId)
            ->update(['image' => $filename]);

        return redirect()->route('admin.gametypes.index');
    }

    public function toggleStatus($id)
    {
        $game = GameList::findOrFail($id);
        $game->status = $game->status == 1 ? 0 : 1;
        $game->save();

        return redirect()->route('admin.gameLists.index')->with('success', 'Game status updated successfully.');
    }

    public function HotGameStatus($id)
    {
        $game = GameList::findOrFail($id);
        $game->hot_status = $game->hot_status == 1 ? 0 : 1;
        $game->save();


        return redirect()->route('admin.gameLists.index')->with('success', 'HotGame status updated successfully.');
    }
}
