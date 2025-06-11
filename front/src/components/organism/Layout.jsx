import { Outlet } from "react-router-dom";
import Navbar from "./Navbar";
import SideBar from "./SideBar";


function Layout() {
    return (
        <div className="d-flex">
            <SideBar />
            <div className="flex-grow-1">
                <Navbar />
                <main className="p-4">
                    {/* <CreateAGoal /> */}
                    <Outlet />
                    {/* <h1 className="text-center">Bienvenue sur le dashboard</h1> */}
                </main>
            </div>
        </div>
    );
}

export default Layout;
