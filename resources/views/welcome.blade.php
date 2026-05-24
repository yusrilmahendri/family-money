<!DOCTYPE html>
<html>

   <!-- file css -->
   @include('templates.partials.head')

<body>

    <!-- file navbar -->
	@include('templates.partials.navbar')

    <!-- file sidebar -->
    @include('templates.partials.sidebar')


	<div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
		
		<!-- content -->
		<div class="row">
			<div class="col-md-12 col-sm-6 col-lg-12">
				<div class="panel panel-default">
					 @yield('content')
				</div>
			</div>
		</div>

		<div class="row">
			<div class="col-md-12">
				<div class="panel panel-default">
					 @yield('card')
				</div>
			</div>
		</div>

		<div class="col-sm-12">
				<p class="back-link" style="margin-top:10px;">
					 <strong>Copyright &copy; 2021<a href="https://www.instagram.com/yusril.mahendri/"> Yusril Mahendri</a>.</strong> version 0.1 
                </p>
			</div>
		</div>
	</div>

     <!-- script js -->
     @include('templates.partials.scripts')

     <!-- AI Asisten (floating chatbot) -->
     @include('templates.partials.ai_chat')

</body>
</html>