<?php include '../absoluto.php';
include(HEADER_FILE);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - Exemplo</title>

  <!-- Bootstrap CSS -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet" />

  <!-- Estilos adicionais -->
  <style>
    body {
      background-color: #f5f5f5;
    }

    /* Ajuste de espaçamento entre cards e seções */
    .dashboard-section {
      margin-bottom: 30px;
    }

    .card-custom {
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      border: none;
      border-radius: 8px;
      margin-bottom: 20px;
    }

    .card-custom h5 {
      margin: 0;
    }

    .chart-container {
      background-color: #fff;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .filter-section {
      background-color: #e9ecef;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 20px;
    }

    /* Tabela estilizada */
    .table-custom {
      background-color: #fff;
      border-radius: 8px;
      padding: 15px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .table-custom table {
      width: 100%;
    }
  </style>
</head>

<body>
  <div class="container-fluid">
    <div class="row">
      <!-- Coluna lateral (Filtros) -->
      <div class="col-md-2 p-3">
        <div class="filter-section">
          <h5>Filtros</h5>
          <hr />
          <!-- Exemplos de filtros -->
          <div class="mb-3">
            <label for="filtroCategoria" class="form-label">Categoria</label>
            <select class="form-select" id="filtroCategoria">
              <option>Todos</option>
              <option>Categoria A</option>
              <option>Categoria B</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="filtroSubcategoria" class="form-label">Subcategoria</label>
            <select class="form-select" id="filtroSubcategoria">
              <option>Todos</option>
              <option>Sub A</option>
              <option>Sub B</option>
            </select>
          </div>
          <button class="btn btn-primary w-100">Aplicar</button>
        </div>
      </div>

      <!-- Conteúdo principal do Dashboard -->
      <div class="col-md-10 p-4">
        <h2 class="mb-4">Vendas Ano x Ano</h2>

        <!-- Cards superiores (Faturamento, etc.) -->
        <div class="row dashboard-section">
          <!-- Card 1 -->
          <div class="col-md-3">
            <div class="card card-custom text-center p-3">
              <h5>R$85M</h5>
              <p class="mb-0">Faturamento</p>
            </div>
          </div>
          <!-- Card 2 -->
          <div class="col-md-3">
            <div class="card card-custom text-center p-3">
              <h5>R$67M</h5>
              <p class="mb-0">Faturamento Ano Anterior</p>
            </div>
          </div>
          <!-- Card 3 -->
          <div class="col-md-3">
            <div class="card card-custom text-center p-3">
              <h5>27,2%</h5>
              <p class="mb-0">% Variação YoY</p>
            </div>
          </div>
          <!-- Card 4 (Exemplo adicional) -->
          <div class="col-md-3">
            <div class="card card-custom text-center p-3">
              <h5>...</h5>
              <p class="mb-0">Outro Indicador</p>
            </div>
          </div>
        </div>

        <!-- Gráficos -->
        <div class="row dashboard-section">
          <!-- Gráfico 1 (Barra) -->
          <div class="col-md-7">
            <div class="chart-container mb-3">
              <canvas id="barChart"></canvas>
            </div>
          </div>
          <!-- Gráfico 2 (YOY) -->
          <div class="col-md-5">
            <div class="chart-container mb-3">
              <canvas id="yoyChart"></canvas>
            </div>
          </div>
        </div>

        <!-- Tabela e/ou Lista de Categorias -->
        <div class="row dashboard-section">
          <div class="col-md-4">
            <!-- Exemplo de lista de categorias -->
            <div class="table-custom">
              <h5>Categorias</h5>
              <table class="table table-striped table-sm mt-2">
                <thead>
                  <tr>
                    <th>Categoria</th>
                    <th>Ano Atual</th>
                    <th>Ano Anterior</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Bebidas</td>
                    <td>R$ 9,8M</td>
                    <td>R$ 8,1M</td>
                  </tr>
                  <tr>
                    <td>Vestuário</td>
                    <td>R$ 12,5M</td>
                    <td>R$ 11,2M</td>
                  </tr>
                  <tr>
                    <td>Alimentos</td>
                    <td>R$ 15,2M</td>
                    <td>R$ 14,0M</td>
                  </tr>
                  <!-- Adicione mais linhas conforme necessário -->
                </tbody>
              </table>
            </div>
          </div>
          <div class="col-md-8">
            <!-- Espaço adicional para outro conteúdo -->
            <div class="table-custom">
              <h5>Outra Seção ou Tabela</h5>
              <table class="table table-striped table-sm mt-2">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Valor</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Exemplo 1</td>
                    <td>...</td>
                  </tr>
                  <tr>
                    <td>Exemplo 2</td>
                    <td>...</td>
                  </tr>
                  <!-- Adicione mais linhas conforme necessário -->
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div> <!-- Fim do conteúdo principal -->
    </div> <!-- Fim da row -->
  </div> <!-- Fim do container-fluid -->

  <!-- Bootstrap JS -->
  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Chart.js -->
  <script
    src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- Scripts de inicialização dos gráficos -->
  <script>
    // Exemplo de gráfico de barras
    const ctxBar = document.getElementById('barChart').getContext('2d');
    const barChart = new Chart(ctxBar, {
      type: 'bar',
      data: {
        labels: ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'],
        datasets: [{
          label: 'Ano Atual',
          data: [12, 19, 3, 5, 2, 3, 10, 8, 12, 14, 7, 9],
          backgroundColor: '#3498db'
        }, {
          label: 'Ano Anterior',
          data: [10, 15, 4, 7, 3, 2, 9, 6, 10, 12, 5, 8],
          backgroundColor: '#2ecc71'
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'top'
          },
          title: {
            display: true,
            text: 'Faturamento Mensal'
          }
        }
      }
    });

    // Exemplo de gráfico de barras para YOY
    const ctxYoy = document.getElementById('yoyChart').getContext('2d');
    const yoyChart = new Chart(ctxYoy, {
      type: 'bar',
      data: {
        labels: ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'],
        datasets: [{
          label: '% YoY',
          data: [5.2, 8.0, 2.3, 10.5, 7.8, 3.2, 9.1, 4.4, 6.5, 5.0, 7.7, 3.8],
          backgroundColor: '#f39c12'
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'top'
          },
          title: {
            display: true,
            text: 'Variação YoY por Mês'
          }
        },
        scales: {
          y: {
            ticks: {
              callback: function(value) {
                return value + '%';
              }
            }
          }
        }
      }
    });
  </script>
</body>
<?php include(FOOTER_FILE); ?>

</html>