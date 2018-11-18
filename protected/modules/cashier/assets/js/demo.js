(function() {
	'use strict';

  var pr=[20,40,60,80, 95];
  document.querySelector("#p0").addEventListener('mdl-componentupgraded', function() {
    this.MaterialProgress.setProgress(pr[0]);
  });
  document.querySelector("#p1").addEventListener('mdl-componentupgraded', function() {
    this.MaterialProgress.setProgress(pr[1]);
  });
  document.querySelector("#p2").addEventListener('mdl-componentupgraded', function() {
    this.MaterialProgress.setProgress(pr[2]);
  });
  document.querySelector("#p3").addEventListener('mdl-componentupgraded', function() {
    this.MaterialProgress.setProgress(pr[3]);
  });
  document.querySelector("#p4").addEventListener('mdl-componentupgraded', function() {
    this.MaterialProgress.setProgress(pr[4]);
  });

  $('.mdl-layout__content').addClass('scroll');

function tgl(s,tipe,pisah){
  var tgl = null;
  switch(tipe){
    case 'ymd':
      var bulan = new Date(s).getMonth()+1;
      tgl = s.split(' ');
      return tgl[3]+pisah+bulan+pisah+tgl[2];
    case 'dmy':
      var bulan = new Date(s).getMonth()+1;
      tgl = s.split(' ');
      return tgl[2]+pisah+bulan+pisah+tgl[3];
    case 'waktu':
      tgl = s.split(' ');
      return tgl[4];

    default:
    return console.log('default');

  }
}

  var theme_used = "";
  $('[name="opt-layout-theme"]').on('change', function(){
    $('.mdl-layout').removeClass(theme_used);    
    $('.mdl-layout').addClass("mdl-layout__" + $(this).val());    
    theme_used = "mdl-layout__" + $(this).val();
  });

  var sidebar_color_used = "";
  $('[name="opt-layout-sidebar"]').on('change', function(){
    $('.mdl-layout').removeClass(sidebar_color_used);    
    $('.mdl-layout').addClass("mdl-layout__" + $(this).val());    
    sidebar_color_used = "mdl-layout__" + $(this).val();
  });

var bg_color = ["indigo", "amber", "blue_grey", "blue", "brown", "cyan", "deep_orange", "green", "grey", "light_blue", "light_green", "lime", "orange", "pink", "purple", "red", "teal", "yellow", "dark", "white"];
var skin_used = "";
  $('.btn-choose__skin').on('click', function(){
    var check_color = $(this).attr('class').split(" ");
    for (var i = 0; i < check_color.length; i++) {
      for (var j = 0; j < bg_color.length; j++) {
        if (check_color[i] === "bg-"+bg_color[j]) {
          $('.mdl-layout').removeClass(skin_used);
          $('.mdl-layout').addClass("mdl-layout__"+ bg_color[j] +"-skin");
          skin_used = "mdl-layout__"+ bg_color[j] +"-skin";
        }
      }
    }
  });


    $('.x-badge__close').on('click', function(){
      $(this).parent('.mdl-list__item').remove();
    });


  	var url = location.pathname.split("/");
  	url = url[url.length - 1];

    var randomScalingFactor = function() {
      return Math.round(Math.random() * 100);
    };


  /* ------------- index.html ----------------*/
	if (url === "index.html" || url === "" || url === "horizontal.html" || url === "expanded.html") {

		$('#btn-demo_todoList').on('click', function(){
			var txt = $('#demo_todoList');
			var a = "";
		    a+='<li class="mdl-list__item">';
		      a+='<span class="mdl-list__item-primary-content">'+ txt.val() +'</span>';
		      a+='<span class="x-icon x-badge__close material-icons">close</span>';
		    a+='</li>';
			$('.demo_ul_todoList').prepend(a);
			txt.val(""); 	
		  $('.x-badge__close').on('click', function(){
		  	$(this).parent('.mdl-list__item').remove();
		  });
		});

/*	   document.querySelector("#demo-report1").addEventListener('mdl-componentupgraded', function() {
			this.MaterialProgress.setProgress(pr[0]);
	    });
	   document.querySelector("#demo-report2").addEventListener('mdl-componentupgraded', function() {
			$(this).children('.progressbar.bar.bar1').addClass('mdl-color--pink');
			this.MaterialProgress.setProgress(pr[1]);
	    });
	   document.querySelector("#demo-report3").addEventListener('mdl-componentupgraded', function() {
			$(this).children('.progressbar.bar.bar1').addClass('mdl-color--orange-900');
			this.MaterialProgress.setProgress(pr[2]);
	    });
	   document.querySelector("#demo-report4").addEventListener('mdl-componentupgraded', function() {
			$(this).children('.progressbar.bar.bar1').addClass('mdl-color--blue');
			this.MaterialProgress.setProgress(pr[3]);
	    });
	   document.querySelector("#demo-report5").addEventListener('mdl-componentupgraded', function() {
			$(this).children('.progressbar.bar.bar1').addClass('mdl-color--grey-900');
			this.MaterialProgress.setProgress(pr[4]);
	    });
*/


    var color = Chart.helpers.color;

    /*vertical bar chart*/
    var barChartData = {
      labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July'],
      datasets: [{
        label: 'Dataset 1',
        backgroundColor: color(window.chartColors.red).alpha(0.5).rgbString(),
        borderColor: window.chartColors.red,
        borderWidth: 1,
        data: [
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor()
        ]
      }, {
        label: 'Dataset 2',
        backgroundColor: color(window.chartColors.blue).alpha(0.5).rgbString(),
        borderColor: window.chartColors.blue,
        borderWidth: 1,
        data: [
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor()
        ]
      }]

    };

    /*end vertical bar chart*/

    /*doughnut chart*/

    var doughnutChart = {
      type: 'doughnut',
      data: {
        datasets: [{
          data: [
            randomScalingFactor(),
            randomScalingFactor(),
            randomScalingFactor(),
            randomScalingFactor(),
            randomScalingFactor(),
            randomScalingFactor(),
          ],
          backgroundColor: [
            window.chartColors.yellow,
            window.chartColors.orange,
            window.chartColors.red,
            window.chartColors.green,
            window.chartColors.blue,
            window.chartColors.grey
          ],
          label: 'Dataset 1'
        }],
        labels: [
          'Chrome',
          'Firefox',
          'Opera',
          'Navigator',
          'Safari',
          'IE'
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: {
          position: 'right',
        },
        title: {
          display: false,
          text: 'Chart.js Doughnut Chart'
        },
        animation: {
          animateScale: true,
          animateRotate: true
        }
      }
    };
    /*end doughnut chart*/

    /*load all chart*/
    window.onload = function() {

      /*vertilal bar chart*/
      var bar_ctx = document.getElementById('serverLoad').getContext('2d');
      window.myBar = new Chart(bar_ctx, {
        type: 'bar',
        data: barChartData,
        options: {
          // responsive: true,
          maintainAspectRatio:false,
          legend: {
            position: 'top',
            fontColor: 'red'
          },
          title: {
            display: false,
            text: 'Chart.js Bar Chart'
          },
          scales: {
              yAxes: [{
                  ticks: {
                      beginAtZero:true,
                      fontColor: '#999'
                  },
                  gridLines: {
                      display: false,
                   }
              }],
            xAxes: [{
                  ticks: {
                      fontColor: '#999'
                  },
                  gridLines: {
                      display: false,
                   }
              }]
          } 
        }
      });
      /*end vertical var chart*/

      /*doughnut chart*/
      var ctx = document.getElementById('donut').getContext('2d');
      window.myDoughnut = new Chart(ctx, doughnutChart);
      /*end doughnut chart*/

    };


	} /*end index.html*/


  /* ------------- morris.html ----------------*/
  if (url === "morris.html") {

  var randomNumber = function() {
      return Math.round(Math.random() * 100);
    };
  $(function () {
    "use strict";

    /*BAR CHART*/
    var bar = new Morris.Bar({
      element: 'bar_chart',
      resize: true,
      data: [
        {y: '2012', a: 100, b: 90},
        {y: '2013', a: 75, b: 65},
        {y: '2014', a: 50, b: 40},
        {y: '2015', a: 75, b: 65},
        {y: '2016', a: 50, b: 40},
        {y: '2017', a: 75, b: 65},
        {y: '2018', a: 100, b: 90}
      ],
      // barColors: ['rgb(255, 99, 132)', 'rgb(255, 159, 64)'],
      stacked:false,
      xkey: 'y',
      ykeys: ['a', 'b'],
      labels: ['CPU', 'DISK'],
      hideHover: 'auto'
    });
    /*end bar chart*/

   /*LINE CHART*/
    new Morris.Line({
      element: 'line_chart',
      data: [
        { year: '2007', value: randomNumber() },
        { year: '2008', value: randomNumber() },
        { year: '2009', value: randomNumber() },
        { year: '2010', value: randomNumber() },
        { year: '2011', value: randomNumber() },
        { year: '2012', value: randomNumber() },
        { year: '2013', value: randomNumber() },
        { year: '2014', value:randomNumber() },
        { year: '2015', value:randomNumber() },
        { year: '2016', value: randomNumber() },
        { year: '2017', value: randomNumber() },
        { year: '2018', value: randomNumber() }
      ],
      lineWidth:'2',
      pointStrokeColors:'#0b62a4',
      xkey: 'year',
      ykeys: ['value'],
      labels: ['Value'],
      hideHover: 'auto',
      resize:'true'
    });
    /*end line chart*/

    /*area chart*/
    var area = new Morris.Area({
      element: 'area_chart',
      resize: true,
      data: [
        { year: '2007', item1: randomNumber(), item2: randomNumber() },
        { year: '2008', item1: randomNumber(), item2: randomNumber() },
        { year: '2009', item1: randomNumber(), item2: randomNumber() },
        { year: '2010', item1: randomNumber(), item2: randomNumber() },
        { year: '2011', item1: randomNumber(), item2: randomNumber() },
        { year: '2012', item1: randomNumber(), item2: randomNumber() },
        { year: '2013', item1: randomNumber(), item2: randomNumber() },
        { year: '2014', item1: randomNumber(), item2: randomNumber() },
        { year: '2015', item1: randomNumber(), item2: randomNumber() },
        { year: '2016', item1: randomNumber(), item2: randomNumber() },
        { year: '2017', item1: randomNumber(), item2: randomNumber() },
        { year: '2018', item1: randomNumber(), item2: randomNumber() }
      ],
      xkey: 'year',
      lineWidth:'2',
      pointStrokeColors:['#a0d0e0', '#3c8dbc'],
      // lineColors: ['#a0d0e0', '#3c8dbc'],
      ykeys: ['item1', 'item2'],
      labels: ['Item 1', 'Item 2'],
      hideHover: 'auto'
    });
    /*end area chart*/

    /*donut chart*/
    var donut = new Morris.Donut({
      element: 'donut_chart',
      resize: true,
      colors: ["#4dc9f6", "#f56954", "#f67019"],
      data: [
        {label: "Download Sales", value: 12},
        {label: "In-Store Sales", value: 30},
        {label: "Mail-Order Sales", value: 20}
      ],
      hideHover: 'auto'
    });
    /*end donut chart*/
  });

  } /*end morris.html*/


  /* ------------- colors.html ----------------*/
  if (url === "colors.html") {
      var array = ["yellow","lime","light-green","green","teal","cyan","light-blue","blue","indigo","deep-purple","purple","pink","red","deep-orange","orange","amber","grey","blue-grey","brown"];
      var a =""; 
      var ab =""; 
          var number = 100;
        for (var i = 0; i < array.length; i++) {
          ab+='<div class="mdl-cell mdl-cell--4-col-phone mdl-cell--3-col-desktop">';
          ab+='<ul class="demo-colors">';
          for (var j = 1; j <= 9; j++) {
            ab+='<li class=" mdl-color--'+array[i]+'-'+ j * number+'" >.mdl-color--'+array[i]+'-'+ j * number+'</li>';
          }
          ab+='</ul>';
          ab+='</div>';
        }
        $('.demo-bg-color').html(ab);

        for (var i = 0; i < array.length; i++) {
          a+='<div class="mdl-cell mdl-cell--4-col-phone mdl-cell--3-col-desktop">';
          a+='<ul class="demo-colors">';
          for (var j = 1; j <= 9; j++) {
          a+='<li class=" mdl-color-text--'+array[i]+'-'+ j * number+'" >.mdl-color-text--'+array[i]+'-'+ j * number+'</li>';
          }
          a+='</ul>';
          a+='</div>';
        }
    $('.demo-color-text').html(a);
  } /*end colors.html*/


  /* ------------- snackbar.html ----------------*/
  if (url === "snackbar.html") {

      (function() {
        'use strict';
        var snackbarContainer = document.querySelector('#demo-snackbar-example');
        var showSnackbarButton = document.querySelector('#demo-show-snackbar');
        var handler = function(event) {
          showSnackbarButton.style.backgroundColor = '';
        };
        showSnackbarButton.addEventListener('click', function() {
          'use strict';
          showSnackbarButton.style.backgroundColor = '#' +
              Math.floor(Math.random() * 0xFFFFFF).toString(16);
          var data = {
            message: 'Button color changed.',
            timeout: 2000,
            actionHandler: handler,
            actionText: 'Undo'
          };
          snackbarContainer.MaterialSnackbar.showSnackbar(data);
        });
      }());

      (function() {
        'use strict';
        window['counter'] = 0;
        var snackbarContainer = document.querySelector('#demo-toast-example');
        var showToastButton = document.querySelector('#demo-show-toast');
        showToastButton.addEventListener('click', function() {
          'use strict';
          var data = {message: 'Example Message # ' + ++counter};
          snackbarContainer.MaterialSnackbar.showSnackbar(data);
        });
      }());

  } /*snackbar.html*/


/*---------------- form-elements.html ---------------*/
if (url === "form-elements.html") {

    var x = new mdDateTimePicker.default({
      type: 'date'
    });
    var y = new mdDateTimePicker.default({
      type: 'date',
      orientation: 'PORTRAIT'
    });
    document.getElementById('trigger').addEventListener('click', function() {
      x.toggle();
    });
    document.getElementById('trigger2').addEventListener('click', function() {
      y.toggle();
    });
    // dispatch event test
    x.trigger = document.getElementById('trigger');
    y.trigger = document.getElementById('trigger2');

    document.getElementById('trigger').addEventListener('onOk', function() {
      this.value = tgl(x.time.toString(),'ymd','/');
    });
    document.getElementById('trigger2').addEventListener('onOk', function() {
      this.value = tgl(y.time.toString(),'ymd','/');
    });

    var time1 = new mdDateTimePicker.default({
      type: 'time'
    });
    var time2 = new mdDateTimePicker.default({
      type: 'time',
      init: moment('10:5 PM', 'h:m A'),
      orientation: 'PORTRAIT'
    });
    var time3 = new mdDateTimePicker.default({
      type: 'time',
      init: moment('22:0', 'H:m'),
      mode: true,
      orientation: 'PORTRAIT'
    });
    document.getElementById('trigger3').addEventListener('click', function() {
      time1.toggle();
    });
    document.getElementById('trigger4').addEventListener('click', function() {
      time2.toggle();
    });
    document.getElementById('trigger5').addEventListener('click', function() {
      time3.toggle();
    });

    time1.trigger = document.getElementById('trigger3');
    document.getElementById('trigger3').addEventListener('onOk', function() {
      this.value = tgl(time1.time.toString(),'waktu','');
    });
    time2.trigger = document.getElementById('trigger4');
    document.getElementById('trigger4').addEventListener('onOk', function() {
      this.value = tgl(time2.time.toString(),'waktu','');
    });

    time3.trigger = document.getElementById('trigger5');
    document.getElementById('trigger5').addEventListener('onOk', function() {
      this.value = tgl(time3.time.toString(),'waktu','');
    });

}

  /* ------------- form-stepper.html ----------------*/
  if (url === "form-stepper.html") {
        $(function ()
        {
            $("#wizard").steps({
                headerTag: "h2",
                bodyTag: "section",
                transitionEffect: "slideLeft",
                titleTemplate:"<span class='number'>#index#.</span> #title#"
            });
            $("#wizard_v").steps({
                headerTag: "h2",
                bodyTag: "section",
                transitionEffect: "slideLeft",
                stepsOrientation: $.fn.steps.stepsOrientation.vertical,
                titleTemplate:"<span class='number'>#index#.</span> #title#"
            });
            $('.wizard .actions a').addClass('mdl-button mdl-button--raised mdl-js-button');
        });
  } /* end form-stepper */


  /* ------------- datatables.html ----------------*/
  if (url === "datatables.html") {
    $('#example').DataTable({
      'autoWidth': true,
      'lengthChange': true,
      'scrollX': true,
      columnDefs: [
          {
              targets: [ 0, 1, 2 ],
              className: 'mdl-data-table__cell--non-numeric'
          }
      ]
    });
  } /*end datatables.html*/


  /* ------------- chart-js.html ----------------*/
  if (url === "chart-js.html") {

    var color = Chart.helpers.color;

    /*vertical bar chart*/
    var barChartData = {
      labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July'],
      datasets: [{
        label: 'Dataset 1',
        backgroundColor: color(window.chartColors.red).alpha(0.5).rgbString(),
        borderColor: window.chartColors.red,
        borderWidth: 1,
        data: [
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor()
        ]
      }, {
        label: 'Dataset 2',
        backgroundColor: color(window.chartColors.blue).alpha(0.5).rgbString(),
        borderColor: window.chartColors.blue,
        borderWidth: 1,
        data: [
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor(),
          randomScalingFactor()
        ]
      }]

    };

    document.getElementById('random_vert_bar_chart').addEventListener('click', function() {
      var zero = Math.random() < 0.2 ? true : false;
      barChartData.datasets.forEach(function(dataset) {
        dataset.data = dataset.data.map(function() {
          return zero ? 0.0 : randomScalingFactor();
        });

      });
      window.myBar.update();
    });
    /*end vertical bar chart*/

    /*line chart*/
    var config = {
      type: 'line',
      data: {
        labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July'],
        datasets: [{
          label: 'My First dataset',
          backgroundColor: window.chartColors.red,
          borderColor: window.chartColors.red,
          data: [
            randomScalingFactor(),
            randomScalingFactor(),
            randomScalingFactor(),
            randomScalingFactor(),
            randomScalingFactor(),
            randomScalingFactor(),
            randomScalingFactor()
          ],
          fill: false,
        }, {
          label: 'My Second dataset',
          fill: false,
          backgroundColor: window.chartColors.blue,
          borderColor: window.chartColors.blue,
          data: [
            randomScalingFactor(),
            randomScalingFactor(),
            randomScalingFactor(),
            randomScalingFactor(),
            randomScalingFactor(),
            randomScalingFactor(),
            randomScalingFactor()
          ],
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio:false,
        title: {
          display: true,
          text: 'Chart.js Line Chart'
        },
        tooltips: {
          mode: 'index',
          intersect: false,
        },
        hover: {
          mode: 'nearest',
          intersect: true
        },
        scales: {
          xAxes: [{
            display: true,
            scaleLabel: {
              display: true,
              labelString: 'Month'
            }
          }],
          yAxes: [{
            display: true,
            scaleLabel: {
              display: true,
              labelString: 'Value'
            }
          }]
        }
      }
    };
    document.getElementById('random_line_chart').addEventListener('click', function() {
      config.data.datasets.forEach(function(dataset) {
        dataset.data = dataset.data.map(function() {
          return randomScalingFactor();
        });

      });

      window.myLine.update();
    });
    /*end line chart*/

    /*radar chart*/

    var radndomRadarData = function() {
      return Math.round(Math.random() * 100);
    };
    var radarData = {
      type: 'radar',
      data: {
        labels: [['Eating', 'Dinner'], ['Drinking', 'Water'], 'Sleeping', ['Designing', 'Graphics'], 'Coding', 'Cycling', 'Running'],
        datasets: [{
          label: 'My First dataset',
          backgroundColor: color(window.chartColors.red).alpha(0.2).rgbString(),
          borderColor: window.chartColors.red,
          pointBackgroundColor: window.chartColors.red,
          data: [
            radndomRadarData(),
            radndomRadarData(),
            radndomRadarData(),
            radndomRadarData(),
            radndomRadarData(),
            radndomRadarData(),
            radndomRadarData()
          ]
        }, {
          label: 'My Second dataset',
          backgroundColor: color(window.chartColors.blue).alpha(0.2).rgbString(),
          borderColor: window.chartColors.blue,
          pointBackgroundColor: window.chartColors.blue,
          data: [
            radndomRadarData(),
            radndomRadarData(),
            radndomRadarData(),
            radndomRadarData(),
            radndomRadarData(),
            radndomRadarData(),
            radndomRadarData()
          ]
        }]
      },
      options: {
        maintainAspectRatio:false,
        legend: {
          position: 'top',
        },
        title: {
          display: true,
          text: 'Chart.js Radar Chart'
        },
        scale: {
          ticks: {
            beginAtZero: true
          }
        }
      }
    };
    document.getElementById('random_radar_chart').addEventListener('click', function() {
      radarData.data.datasets.forEach(function(dataset) {
        dataset.data = dataset.data.map(function() {
          return radndomRadarData();
        });
      });

      window.myRadar.update();
    });

    /*end radar chart*/

    /*doughnut chart*/
    var randomDoughnutData = function() {
      return Math.round(Math.random() * 100);
    };
    var doughnutChart = {
      type: 'doughnut',
      data: {
        datasets: [{
          data: [
            randomDoughnutData(),
            randomDoughnutData(),
            randomDoughnutData(),
            randomDoughnutData(),
            randomDoughnutData(),
          ],
          backgroundColor: [
            window.chartColors.red,
            window.chartColors.orange,
            window.chartColors.yellow,
            window.chartColors.green,
            window.chartColors.blue,
          ],
          label: 'Dataset 1'
        }],
        labels: [
          'Red',
          'Orange',
          'Yellow',
          'Green',
          'Blue'
        ]
      },
      options: {
        maintainAspectRatio:false,
        responsive: true,
        legend: {
          position: 'top',
        },
        title: {
          display: true,
          text: 'Chart.js Doughnut Chart'
        },
        animation: {
          animateScale: true,
          animateRotate: true
        }
      }
    };

    document.getElementById('random_doughnut_chart').addEventListener('click', function() {
      doughnutChart.data.datasets.forEach(function(dataset) {
        dataset.data = dataset.data.map(function() {
          return randomDoughnutData();
        });
      });

      window.myDoughnut.update();
    });
    /*end doughnut chart*/

    /*polar area chart*/
    var randomPolarAreaData = function() {
      return Math.round(Math.random() * 100);
    };
    var chartColors = window.chartColors;
    var polarAreaChart = {
      data: {
        datasets: [{
          data: [
            randomPolarAreaData(),
            randomPolarAreaData(),
            randomPolarAreaData(),
            randomPolarAreaData(),
            randomPolarAreaData(),
          ],
          backgroundColor: [
            color(chartColors.red).alpha(0.5).rgbString(),
            color(chartColors.orange).alpha(0.5).rgbString(),
            color(chartColors.yellow).alpha(0.5).rgbString(),
            color(chartColors.green).alpha(0.5).rgbString(),
            color(chartColors.blue).alpha(0.5).rgbString(),
          ],
          label: 'My dataset' // for legend
        }],
        labels: [
          'Red',
          'Orange',
          'Yellow',
          'Green',
          'Blue'
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio:false,
        legend: {
          position: 'top',
        },
        title: {
          display: true,
          text: 'Chart.js Polar Area Chart'
        },
        scale: {
          ticks: {
            beginAtZero: true
          },
          reverse: false
        },
        animation: {
          animateRotate: false,
          animateScale: true
        }
      }
    };
    document.getElementById('random_polarArea_chart').addEventListener('click', function() {
      polarAreaChart.data.datasets.forEach(function(piece, i) {
        piece.data.forEach(function(value, j) {
          polarAreaChart.data.datasets[i].data[j] = randomPolarAreaData();
        });
      });
      window.myPolarArea.update();
    });
    /*end polar area chart*/


    /*load all chart*/
    window.onload = function() {
      /*vertilal bar chart*/
      var bar_ctx = document.getElementById('vertical_bar_chart').getContext('2d');
      window.myBar = new Chart(bar_ctx, {
        type: 'bar',
        data: barChartData,
        options: {
          // responsive: true,
          maintainAspectRatio:false,
          legend: {
            position: 'top',
          },
          title: {
            display: true,
            text: 'Chart.js Bar Chart'
          }
        }
      });
      /*end vertical var chart*/

      /*line chart*/
      var ctx = document.getElementById('line_chart').getContext('2d');
      window.myLine = new Chart(ctx, config);
      /*end line chart*/

      /*radar chart*/
      window.myRadar = new Chart(document.getElementById('radar_chart'), radarData);
      /*end radar chart*/

      /*doughnut chart*/
      var ctx = document.getElementById('doughnut_chart').getContext('2d');
      window.myDoughnut = new Chart(ctx, doughnutChart);
      /*end doughnut chart*/

      /*polarArea chart*/
      var ctx = document.getElementById('polarArea_chart');
      window.myPolarArea = Chart.PolarArea(ctx, polarAreaChart);
      /*end polarArea chart*/
    };


  } /* end chart-js.html */

}());
