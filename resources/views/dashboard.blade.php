@extends('layouts.app')

@section('title', 'Dashboard - Sistem Penyimpanan Fail Tongod')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 fw-bold text-primary">
        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
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

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">
                            Jumlah Fail
                        </div>
                        <div class="h5 mb-0 font-weight-bold">{{ number_format($stats['total_files']) }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-folder-open fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card-secondary">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">
                            Fail Tersedia
                        </div>
                        <div class="h5 mb-0 font-weight-bold">{{ number_format($stats['available_files']) }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card-warning">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">
                            Fail Dipinjam
                        </div>
                        <div class="h5 mb-0 font-weight-bold">{{ number_format($stats['borrowed_files']) }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-handshake fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Lokasi
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($stats['total_locations']) }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-map-marker-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(auth()->user()->canViewReports() && isset($stats['overdue_files']))
<!-- Additional Stats for Staff -->
<div class="row mb-4">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card stat-card-danger">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">
                            Overdue
                        </div>
                        <div class="h5 mb-0 font-weight-bold">{{ number_format($stats['overdue_files']) }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-warning">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Due Soon
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($stats['due_soon']) }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Pengguna Aktif
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($stats['total_users']) }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<div class="row">
    <!-- Chart -->
    @if(auth()->user()->canViewReports() && !empty($chartData))
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-area me-2"></i>Statistik Tahunan
                </h6>
            </div>
            <div class="card-body">
                <canvas id="myAreaChart" width="100%" height="40"></canvas>
            </div>
        </div>
    </div>
    @endif

    <!-- Recent Activities -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-list me-2"></i>Aktiviti Terkini
                </h6>
            </div>
            <div class="card-body">
                @if($recentActivities->count() > 0)
                    @foreach($recentActivities as $activity)
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                <i class="fas fa-{{ $activity->action === 'login' ? 'sign-in-alt' : ($activity->action === 'file_created' ? 'plus' : 'edit') }} fa-sm"></i>
                            </div>
                        </div>
                        <div class="ms-3">
                            <div class="fw-bold small">{{ $activity->user->name }}</div>
                            <div class="text-muted small">{{ $activity->description }}</div>
                            <div class="text-muted small">{{ $activity->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                    @endforeach
                @else
                    <p class="text-muted text-center">Tiada aktiviti terkini.</p>
                @endif
            </div>
        </div>
    </div>
</div>

@if(auth()->user()->role === 'user_view' && $userBorrowings->count() > 0)
<!-- User's Borrowings -->
<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-handshake me-2"></i>Peminjaman Aktif Anda
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID Fail</th>
                                <th>Tajuk</th>
                                <th>Tarikh Pinjam</th>
                                <th>Tarikh Akhir</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($userBorrowings as $borrowing)
                            <tr>
                                <td>{{ $borrowing->file->file_id }}</td>
                                <td>{{ $borrowing->file->title }}</td>
                                <td>{{ $borrowing->borrowed_date->format('d/m/Y') }}</td>
                                <td>{{ $borrowing->due_date->format('d/m/Y') }}</td>
                                <td>
                                    @if($borrowing->isOverdue())
                                        <span class="badge bg-danger">Overdue</span>
                                    @elseif($borrowing->days_remaining <= 3)
                                        <span class="badge bg-warning">Due Soon</span>
                                    @else
                                        <span class="badge bg-success">Aktif</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@if(auth()->user()->canViewReports() && !empty($chartData))
@push('scripts')
<script>
// Chart Configuration
Chart.defaults.global.defaultFontFamily = 'Inter', '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
Chart.defaults.global.defaultFontColor = '#858796';

function number_format(number, decimals, dec_point, thousands_sep) {
  number = (number + '').replace(',', '').replace(' ', '');
  var n = !isFinite(+number) ? 0 : +number,
    prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
    sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
    dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
    s = '',
    toFixedFix = function(n, prec) {
      var k = Math.pow(10, prec);
      return '' + Math.round(n * k) / k;
    };
  s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
  if (s[0].length > 3) {
    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
  }
  if ((s[1] || '').length < prec) {
    s[1] = s[1] || '';
    s[1] += new Array(prec - s[1].length + 1).join('0');
  }
  return s.join(dec);
}

// Area Chart
var ctx = document.getElementById("myAreaChart");
var myLineChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: {!! json_encode($chartData['months']) !!},
    datasets: [{
      label: "Fail Dicipta",
      lineTension: 0.3,
      backgroundColor: "rgba(78, 115, 223, 0.05)",
      borderColor: "rgba(78, 115, 223, 1)",
      pointRadius: 3,
      pointBackgroundColor: "rgba(78, 115, 223, 1)",
      pointBorderColor: "rgba(78, 115, 223, 1)",
      pointHoverRadius: 3,
      pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
      pointHoverBorderColor: "rgba(78, 115, 223, 1)",
      pointHitRadius: 10,
      pointBorderWidth: 2,
      data: {!! json_encode($chartData['files_created']) !!},
    }, {
      label: "Fail Dipinjam",
      lineTension: 0.3,
      backgroundColor: "rgba(28, 200, 138, 0.05)",
      borderColor: "rgba(28, 200, 138, 1)",
      pointRadius: 3,
      pointBackgroundColor: "rgba(28, 200, 138, 1)",
      pointBorderColor: "rgba(28, 200, 138, 1)",
      pointHoverRadius: 3,
      pointHoverBackgroundColor: "rgba(28, 200, 138, 1)",
      pointHoverBorderColor: "rgba(28, 200, 138, 1)",
      pointHitRadius: 10,
      pointBorderWidth: 2,
      data: {!! json_encode($chartData['files_borrowed']) !!},
    }],
  },
  options: {
    maintainAspectRatio: false,
    layout: {
      padding: {
        left: 10,
        right: 25,
        top: 25,
        bottom: 0
      }
    },
    scales: {
      xAxes: [{
        time: {
          unit: 'date'
        },
        gridLines: {
          display: false,
          drawBorder: false
        },
        ticks: {
          maxTicksLimit: 7
        }
      }],
      yAxes: [{
        ticks: {
          maxTicksLimit: 5,
          padding: 10,
          callback: function(value, index, values) {
            return number_format(value);
          }
        },
        gridLines: {
          color: "rgb(234, 236, 244)",
          zeroLineColor: "rgb(234, 236, 244)",
          drawBorder: false,
          borderDash: [2],
          zeroLineBorderDash: [2]
        }
      }],
    },
    legend: {
      display: false
    },
    tooltips: {
      backgroundColor: "rgb(255,255,255)",
      bodyFontColor: "#858796",
      titleMarginBottom: 10,
      titleFontColor: '#6e707e',
      titleFontSize: 14,
      borderColor: '#dddfeb',
      borderWidth: 1,
      xPadding: 15,
      yPadding: 15,
      displayColors: false,
      intersect: false,
      mode: 'index',
      caretPadding: 10,
      callbacks: {
        label: function(tooltipItem, chart) {
          var datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
          return datasetLabel + ': ' + number_format(tooltipItem.yLabel);
        }
      }
    }
  }
});
</script>
@endpush
@endif