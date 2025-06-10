import "./App.css";
// import { SearchBar } from "./components/molécules";
import { Navbar, SideBar } from "./components/organism";

function App() {
  return (
    <div className="d-flex">
      <SideBar />
      <div className="flex-grow-1">
        <Navbar />
        <main className="p-4">
          <h1 className="text-center">Bienvenue sur le dashboard</h1>
        </main>
      </div>
    </div>
  );
}

export default App;
