<?php include 'database.php'; ?>
<?php 
if(isset($_POST['add_program'])){
    $name = $_POST['name']; 
    $duration = $_POST['duration']; 
    $time_slot = $_POST['time_slot']; 
    $channel = $_POST['channel'];

    $sql = "INSERT INTO programs(name, duration, time_slot, channel) 
            VALUES('$name', '$duration', '$time_slot', '$channel')";
            
    if(mysqli_query($conn, $sql)){
        echo "<p style='color:green;'>Program added successfully!</p>";
    } else{
        echo "<p style='color:red;'>Error: ". mysqli_error($conn)."</p>";
    } 
}
?>
<!DOCTYPE html>
<html>
<head>

    <title>Program Manager</title>
    <link rel="stylesheet" href="style.css">   
</head>
<body>


    <h1>Broadcast Programs</h1>
    
    <h2>Add New Program</h2>
<form method="POST" action="index.php">
    <label>Program Name:</label><br>
    <input type="text" name="name" required><br><br>
    
    <label>Duration (minutes):</label><br>
    <input type="number" name="duration" required><br><br>
    
    <label>Time Slot:</label><br>
    <input type="text" name="time_slot" placeholder="e.g. 08:00" required><br><br>
    
    <label>Channel:</label><br>
    <select name="channel" required>
        <option value="SABC1">SABC1</option>
        <option value="SABC2">SABC2</option>
        <option value="SABC3">SABC3</option>
        <option value="eTV">eTV</option>
        <option value="Mzansi Magic">Mzansi Magic</option>
    </select><br><br>
    
    <button type="submit" name="add_program">Add Program</button>
</form>
<hr>
    <table border="1">
        <tr>
            <th>Name</th>
            <th>Duration (min)</th>
            <th>Time Slot</th>
            <th>Channel</th>
        </tr>
        <?php
        // 1. Write SQL query to get all programs
        $sql = "SELECT * FROM programs";

        // 2. Run the query
        $result = mysqli_query($conn, $sql);

        // 3. Loop through results
        while($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>" . $row['name'] . "</td>";
            echo "<td>" . $row['duration'] . "</td>";
            echo "<td>" . $row['time_slot'] . "</td>";
            echo "<td>" . $row['channel'] . "</td>";
            echo "</tr>";
        }
        ?>
    </table>
</body>
</html>