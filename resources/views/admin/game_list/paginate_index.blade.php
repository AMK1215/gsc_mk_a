@extends('admin_layouts.app')
@section('content')
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <!-- Card header -->
            <div class="card-header pb-0">
                <div class="d-lg-flex">
                    <div>
                        <h5 class="mb-4">Game List Dashboards
                        </h5>
                    </div>
                    <div class="ms-auto my-auto mt-lg-0 mt-4">

                    </div>
                </div>
                <form role="form" class="text-start mt-4" action="{{route('admin.gameLists.index')}}" method="GET">
                    <div class="row ml-5">

                        <div class="col-4">
                                <label class="form-label text-dark fw-bold" for="inputEmail1">Search </label>
                                <input type="text" class="form-control border border-1 border-secondary px-2"
                                    id="" name="game_name" value="{{request()->game_name }}">
                        </div>

                        <div class="col-4">
                            <button type="submit" class="btn btn-primary" style="margin-top: 32px;">Search</button>
                            <a href="{{route('admin.gameLists.index')}}" class="btn btn-warning" style="margin-top: 32px;">Refresh</a>
                        </div>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-flush" id="users-search">
                    <thead>
                        <tr class="text-center">
                            <th>#</th>
                            <th class="bg-success text-white">Game Type</th>
                            <th class="bg-danger text-white">Product</th>
                            <th class="bg-info text-white">Game Name</th>
                            <th class="bg-warning text-white">Image</th>
                            <th class="bg-success text-white">Status</th>
                            <th class="bg-info text-white">Hot Status</th>
                            <th class="bg-warning text-white">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(isset($gameLists))
                        @foreach($gameLists as $index => $game)
                            <tr class="text-center">
                                <td>{{ ($gameLists->currentPage() - 1) * $gameLists->perPage() + $loop->iteration }}</td>
                                <td>{{ $game->gameType->name ?? 'N/A' }}</td>
                                <td>{{ $game->product->name }}</td>
                                <td>{{ $game->name }}</td>
                                <td>
                                    <img src="{{$game->image_url}}" width="100px" />
                                </td>
                                <td>
                                    {!! $game->status == 1
                                        ? '<span class="badge badge-success">Open</span>'
                                        : '<span class="badge badge-danger">Close</span>' !!}
                                </td>
                                <td>
                                    {!! $game->hot_status == 1
                                        ? '<span class="badge badge-info">HotGame</span>'
                                        : '<span class="badge badge-warning">NormalGame</span>' !!}
                                </td>
                                <td>
                                    <form action="{{ route('admin.gameLists.toggleStatus', $game->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-info btn-sm">GameStatus</button>
                                    </form>
                                    <form action="{{ route('admin.HotGame.toggleStatus', $game->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-success btn-sm">HotGame</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
                @if(isset($gameLists))
         <div class="d-flex justify-content-center mt-3">
    {{ $gameLists->links() }}
</div>
                @endif



            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('#users-search').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.gameLists.index') }}",
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'game_type', name: 'gameType.name'},
            {data: 'product', name: 'product'},
            {data: 'game_name', name: 'game_name'},
            {data: 'image_url', name: 'image_url', render: function(data, type, full, meta) {
                return '<img src="' + data + '" width="100px">';
            }},
            {data: 'status', name: 'status'},
            {data: 'hot_status', name: 'hot_status'},
            {data: 'action', name: 'action', orderable: false, searchable: false},
        ],
        language: {
            paginate: {
                next: '<i class="fas fa-angle-right"></i>', // or '→'
                previous: '<i class="fas fa-angle-left"></i>' // or '←'
            }
        },
        pageLength: 10, // Adjust this to your preference
    });
});
</script>


@endsection
