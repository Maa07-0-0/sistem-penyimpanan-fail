@extends('layouts.app')

@section('title', 'Pengurusan Fail - Sistem Penyimpanan Fail Tongod')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold text-primary">
        <i class="fas fa-folder me-2"></i>Pengurusan Fail
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            @can('create-files')
            <a href="{{ route('files.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Daftar Fail Baharu
            </a>
            @endcan
        </div>
    </div>
</div>

<!-- Search and Filter Section -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-search me-2"></i>Carian dan Penapis
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('files.index') }}" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Carian</label>
                <input type="text" 
                       class="form-control" 
                       id="search" 
                       name="search" 
                       placeholder="Tajuk, ID fail, atau nombor rujukan..."
                       value="{{ request('search') }}">
            </div>
            
            <div class="col-md-2">
                <label for="department" class="form-label">Jabatan</label>
                <select class="form-select" id="department" name="department">
                    <option value="">Semua Jabatan</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}" {{ request('department') == $dept ? 'selected' : '' }}>
                            {{ $dept }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="document_type" class="form-label">Jenis Dokumen</label>
                <select class="form-select" id="document_type" name="document_type">
                    <option value="">Semua Jenis</option>
                    <option value="surat_rasmi" {{ request('document_type') == 'surat_rasmi' ? 'selected' : '' }}>Surat Rasmi</option>
                    <option value="perjanjian" {{ request('document_type') == 'perjanjian' ? 'selected' : '' }}>Perjanjian</option>
                    <option value="permit" {{ request('document_type') == 'permit' ? 'selected' : '' }}>Permit</option>
                    <option value="laporan" {{ request('document_type') == 'laporan' ? 'selected' : '' }}>Laporan</option>
                    <option value="lain_lain" {{ request('document_type') == 'lain_lain' ? 'selected' : '' }}>Lain-lain</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="tersedia" {{ request('status') == 'tersedia' ? 'selected' : '' }}>Tersedia</option>
                    <option value="dipinjam" {{ request('status') == 'dipinjam' ? 'selected' : '' }}>Dipinjam</option>
                    <option value="arkib" {{ request('status') == 'arkib' ? 'selected' : '' }}>Arkib</option>
                    <option value="tidak_aktif" {{ request('status') == 'tidak_aktif' ? 'selected' : '' }}>Tidak Aktif</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="year" class="form-label">Tahun</label>
                <select class="form-select" id="year" name="year">
                    <option value="">Semua Tahun</option>
                    @foreach($years as $year)
                        <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                            {{ $year }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>Cari
                </button>
                <a href="{{ route('files.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Files Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-list me-2"></i>Senarai Fail
            <span class="badge bg-primary ms-2">{{ $files->total() }}</span>
        </h6>
        
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>Export
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#"><i class="fas fa-file-pdf me-2"></i>PDF</a></li>
                <li><a class="dropdown-item" href="#"><i class="fas fa-file-excel me-2"></i>Excel</a></li>
            </ul>
        </div>
    </div>
    <div class="card-body p-0">
        @if($files->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID Fail</th>
                            <th>Tajuk</th>
                            <th>Jabatan</th>
                            <th>Jenis</th>
                            <th>Tahun</th>
                            <th>Lokasi</th>
                            <th>Status</th>
                            <th>Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($files as $file)
                        <tr>
                            <td>
                                <strong class="text-primary">{{ $file->file_id }}</strong>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ Str::limit($file->title, 40) }}</div>
                                @if($file->reference_number)
                                    <small class="text-muted">Rujukan: {{ $file->reference_number }}</small>
                                @endif
                            </td>
                            <td>{{ $file->department }}</td>
                            <td>
                                <span class="badge bg-info text-dark">{{ $file->document_type_display }}</span>
                            </td>
                            <td>{{ $file->document_year }}</td>
                            <td>
                                @if($file->location)
                                    <small class="text-primary">{{ $file->location->full_location }}</small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($file->status === 'tersedia')
                                    <span class="badge bg-success">{{ $file->status_display }}</span>
                                @elseif($file->status === 'dipinjam')
                                    <span class="badge bg-warning">{{ $file->status_display }}</span>
                                @elseif($file->status === 'arkib')
                                    <span class="badge bg-secondary">{{ $file->status_display }}</span>
                                @else
                                    <span class="badge bg-danger">{{ $file->status_display }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('files.show', $file) }}" 
                                       class="btn btn-outline-primary" 
                                       title="Lihat">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    @can('update', $file)
                                    <a href="{{ route('files.edit', $file) }}" 
                                       class="btn btn-outline-warning" 
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @endcan
                                    
                                    @if($file->canBeBorrowed() && auth()->user()->canBorrowFiles())
                                    <a href="{{ route('borrowings.create', ['file_id' => $file->id]) }}" 
                                       class="btn btn-outline-info" 
                                       title="Pinjam">
                                        <i class="fas fa-handshake"></i>
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Menunjukkan {{ $files->firstItem() }} hingga {{ $files->lastItem() }} 
                        daripada {{ $files->total() }} hasil
                    </div>
                    <div>
                        {{ $files->links() }}
                    </div>
                </div>
            </div>
        @else
            <div class="text-center py-5">
                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Tiada fail dijumpai</h5>
                <p class="text-muted">Cuba ubah kriteria carian anda atau daftar fail baharu.</p>
                @can('create-files')
                <a href="{{ route('files.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Daftar Fail Baharu
                </a>
                @endcan
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
// Auto-submit form on select change for better UX
document.querySelectorAll('select[name="department"], select[name="document_type"], select[name="status"], select[name="year"]').forEach(function(select) {
    select.addEventListener('change', function() {
        if (this.value !== '') {
            this.closest('form').submit();
        }
    });
});
</script>
@endpush